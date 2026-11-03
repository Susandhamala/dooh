<?php

namespace App\Services;

use App\Enums\ReviewStatus;
use App\Exceptions\DomainError;
use App\Models\IdentityDocument;
use App\Models\User;
use App\Repositories\DocumentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Identity documents (KYC). Files are encrypted at rest, readable only by the review team, and
 * deleted as soon as a decision is made: we keep the decision, not the copy of someone's ID.
 */
class DocumentService
{
    public function __construct(private DocumentRepository $documents) {}

    /** @return Collection<int, IdentityDocument> */
    public function forUser(User $user): Collection
    {
        return $this->documents->forUser($user);
    }

    /** none | pending | approved | rejected — from the user's latest document. */
    public function status(User $user): string
    {
        return $this->documents->latestForUser($user)?->status->value ?? 'none';
    }

    public function submit(User $user, array $data, UploadedFile $file): IdentityDocument
    {
        $status = $this->status($user);
        if ($status === ReviewStatus::Approved->value) {
            throw DomainError::conflict('Your identity is already verified.');
        }
        if ($status === ReviewStatus::Pending->value) {
            throw DomainError::conflict('Your document is already being checked. We will let you know soon.');
        }

        $contents = (string) file_get_contents($file->getRealPath());
        $path = 'documents/'.Str::uuid().'.enc';
        Storage::disk('local')->put($path, Crypt::encryptString(base64_encode($contents)));

        return $this->documents->create($user, [
            'type' => $data['type'],
            'number' => trim($data['number']),
            'file_path' => $path,
            'mime' => (string) $file->getMimeType(),
            'sha256' => hash('sha256', $contents),
            'status' => ReviewStatus::Pending,
        ]);
    }

    public function paginateForReview(?ReviewStatus $status): LengthAwarePaginator
    {
        return $this->documents->paginateByStatus($status);
    }

    public function find(string $uuid): IdentityDocument
    {
        return $this->documents->findByUuid($uuid) ?? throw (new ModelNotFoundException)->setModel(IdentityDocument::class);
    }

    /** The decrypted file for a reviewer, or null once it has been deleted. */
    public function file(string $uuid): ?array
    {
        $doc = $this->find($uuid);
        if (! $doc->file_path || ! Storage::disk('local')->exists($doc->file_path)) {
            return null;
        }

        return [
            'contents' => base64_decode(Crypt::decryptString((string) Storage::disk('local')->get($doc->file_path))),
            'mime' => $doc->mime,
        ];
    }

    public function decide(User $reviewer, string $uuid, bool $approve, ?string $note): IdentityDocument
    {
        $doc = $this->find($uuid);
        if ($doc->status !== ReviewStatus::Pending) {
            throw DomainError::conflict('This document has already been decided.');
        }
        if (! $approve && blank($note)) {
            throw new DomainError('Say why the document was refused.', 422, ['note' => ['Give a reason.']]);
        }

        if ($doc->file_path) {
            Storage::disk('local')->delete($doc->file_path);
        }
        $doc->fill([
            'status' => $approve ? ReviewStatus::Approved : ReviewStatus::Rejected,
            'reviewed_by' => $reviewer->getKey(),
            'reviewed_at' => now(),
            'review_note' => $note,
            'file_path' => null,
        ]);

        return $this->documents->save($doc);
    }
}
