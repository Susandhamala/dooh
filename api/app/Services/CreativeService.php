<?php

namespace App\Services;

use App\Enums\CreativeStatus;
use App\Enums\OrganisationType;
use App\Exceptions\DomainError;
use App\Jobs\ProcessCreative;
use App\Models\Creative;
use App\Models\Organisation;
use App\Models\User;
use App\Repositories\CreativeRepository;
use App\Services\Media\CreativeProcessor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Ads ("creatives"): upload → automatic checks and re-encoding → review team → bookable. */
class CreativeService
{
    public function __construct(
        private CreativeRepository $creatives,
        private OrganisationService $orgs,
        private TermsService $terms,
        private CreativeProcessor $processor,
    ) {}

    /** @return Collection<int, Creative> */
    public function list(User $user, string $orgUuid): Collection
    {
        return $this->creatives->forOrganisation($this->org($user, $orgUuid));
    }

    public function find(User $user, string $orgUuid, string $uuid): Creative
    {
        return $this->findIn($this->org($user, $orgUuid), $uuid);
    }

    public function findIn(Organisation $org, string $uuid): Creative
    {
        return $this->creatives->findForOrganisation($org, $uuid) ?? throw (new ModelNotFoundException)->setModel(Creative::class);
    }

    public function upload(User $user, string $orgUuid, array $data, UploadedFile $file): Creative
    {
        $org = $this->org($user, $orgUuid, write: true);
        $mime = (string) $file->getMimeType(); // from the file's bytes (finfo), not its name
        $kind = str_starts_with($mime, 'video/') ? 'video' : 'image';
        [$width, $height] = array_map('intval', explode('x', $data['resolution']));

        $dir = "creatives/{$org->uuid}/".Str::uuid7();
        $original = $file->storeAs($dir, 'original.'.($file->guessExtension() ?: 'bin'), 'local');

        $creative = $this->creatives->create($org, [
            'uploaded_by' => $user->getKey(),
            'name' => trim($data['name']),
            'category' => $data['category'],
            'kind' => $kind,
            'width_px' => $width,
            'height_px' => $height,
            'original_path' => $original,
            'mime' => $mime,
            'size_bytes' => (int) $file->getSize(),
            'status' => CreativeStatus::Processing,
            'declared_at' => now(),
            'terms_version' => $this->terms->version(),
        ]);

        ProcessCreative::dispatch($creative->getKey());

        return $creative;
    }

    /** Runs on the queue: check and re-encode, then hand the ad to the review team. */
    public function process(int $id): void
    {
        $creative = $this->creatives->find($id);
        if (! $creative || $creative->status !== CreativeStatus::Processing) {
            return;
        }

        $disk = Storage::disk('local');
        $dir = dirname((string) $creative->original_path);
        $result = $this->processor->process(
            $disk->path((string) $creative->original_path), $creative->kind, $creative->width_px, $creative->height_px, $disk->path($dir),
        );

        $check = ['errors' => $result['errors'], 'notes' => $result['notes'], 'source' => $result['source'] ?? []];
        if (! $result['ok']) {
            $creative->fill(['status' => CreativeStatus::Failed, 'check_result' => $check]);
            $this->creatives->save($creative);

            return;
        }

        $creative->fill([
            'status' => CreativeStatus::InReview,
            'processed_path' => $dir.'/'.basename($result['processed']),
            'thumb_path' => $result['thumb'] ? $dir.'/'.basename($result['thumb']) : null,
            'mime' => $result['mime'],
            'duration_s' => $result['duration'],
            'sha256' => hash_file('sha256', $result['processed']),
            'size_bytes' => filesize($result['processed']),
            'check_result' => $check,
        ]);
        $this->creatives->save($creative);
    }

    public function delete(User $user, string $orgUuid, string $uuid): void
    {
        $creative = $this->findIn($this->org($user, $orgUuid, write: true), $uuid);
        if ($this->creatives->hasActiveBookings($creative)) {
            throw DomainError::conflict('This ad is booked. Cancel its bookings first.');
        }
        Storage::disk('local')->deleteDirectory(dirname((string) $creative->original_path));
        $this->creatives->delete($creative);
    }

    public function paginateForReview(?CreativeStatus $status): LengthAwarePaginator
    {
        return $this->creatives->paginateByStatus($status);
    }

    public function findAny(string $uuid): Creative
    {
        return $this->creatives->findByUuid($uuid) ?? throw (new ModelNotFoundException)->setModel(Creative::class);
    }

    public function save(Creative $creative): Creative
    {
        return $this->creatives->save($creative);
    }

    /** Absolute path and type of a processed file or thumbnail, for the signed media route. */
    public function media(string $uuid, string $variant): array
    {
        $creative = $this->findAny($uuid);
        $path = $variant === 'thumb' ? $creative->thumb_path : $creative->processed_path;
        if (! $path || ! Storage::disk('local')->exists($path)) {
            throw (new ModelNotFoundException)->setModel(Creative::class);
        }

        return [
            'path' => Storage::disk('local')->path($path),
            'mime' => $variant === 'thumb' ? 'image/jpeg' : $creative->mime,
        ];
    }

    private function org(User $user, string $orgUuid, bool $write = false): Organisation
    {
        return $this->orgs->membership($user, $orgUuid, OrganisationType::Advertiser, $write)->organisation;
    }
}
