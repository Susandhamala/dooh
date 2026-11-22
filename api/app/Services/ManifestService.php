<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Screen;
use App\Repositories\ManifestRepository;
use App\Services\Devices\ManifestSigner;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

/**
 * Signed playlists for screens. The signer (scheduler) builds each screen's playlist for today and
 * tomorrow (Nepal time) and signs a new version only when it changed or is close to expiring. The
 * API serves the stored envelope unchanged, so the web-facing app never holds the signing key.
 *
 * Payload (integers only, times in Unix seconds; see player/dooh_player/manifest.py):
 *   {screen_id, version, valid_from, expires_at, tz_offset_s, loop_seconds, spot_seconds,
 *    items: [{creative_id, url, sha256, duration_s, slot_index, slot_count, windows: [[from, to], ...]}]}
 * The loop restarts at every local hour; slot i plays at i * spot_seconds into each loop. An item
 * takes slot_count back-to-back slots from slot_index (a longer video takes several) and plays
 * there only while now is inside one of its windows.
 *
 * Places come from LoopService: every hour the booked ads are packed back to back, so the same ad
 * can sit at different places in different hours (one item per place, with that hour's windows),
 * and an ad booked to play twice a loop has two places. Windows are shifted by the place's offset,
 * so the ad plays in exactly the loops that start inside its booked time.
 */
class ManifestService
{
    /** Accept a manifest this long before it was signed, for a device clock that runs a little behind. */
    private const CLOCK_SKEW_SECONDS = 300;

    public function __construct(private ManifestRepository $manifests, private ManifestSigner $signer, private LoopService $loops) {}

    /** @return int screens that got a new manifest */
    public function signDue(CarbonImmutable $now): int
    {
        $signed = 0;
        foreach ($this->manifests->screensWithDevices() as $screen) {
            [$content, $expiresAt] = $this->build($screen, $now);
            $hash = hash('sha256', json_encode($content, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $current = $screen->manifest;
            if ($current && $current->content_sha256 === $hash
                && $current->expires_at->getTimestamp() - $now->getTimestamp() > (int) config('dooh.manifest.renew_before_seconds')) {
                continue;
            }

            $payload = [
                'screen_id' => $screen->uuid,
                'version' => max(($current->version ?? 0) + 1, $now->getTimestamp()),
                'valid_from' => $now->getTimestamp() - self::CLOCK_SKEW_SECONDS,
                'expires_at' => $expiresAt,
            ] + $content;
            $bytes = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $this->manifests->save($screen, [
                'version' => $payload['version'],
                'content_sha256' => $hash,
                'payload' => base64_encode($bytes),
                'signature' => $this->signer->sign($bytes),
                'signed_at' => $now,
                'expires_at' => CarbonImmutable::createFromTimestamp($expiresAt),
            ]);
            $signed++;
        }

        return $signed;
    }

    /**
     * What $screen should play today and tomorrow, and when that playlist stops being valid.
     *
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function build(Screen $screen, CarbonImmutable $now): array
    {
        $tz = config('dooh.timezone');
        $today = CarbonImmutable::parse($now->setTimezone($tz)->toDateString());
        $tomorrow = $today->addDay();

        $this->loops->forget();
        $items = [];
        foreach ($this->manifests->playable($screen, $today, $tomorrow) as $booking) {
            $last = $booking->ends_on->min($tomorrow);
            for ($day = $booking->starts_on->max($today); $day->lte($last); $day = $day->addDay()) {
                $midnight = CarbonImmutable::parse($day->toDateString(), $tz)->getTimestamp();
                foreach (LoopService::hoursOf($booking->start_minute, $booking->end_minute) as $h) {
                    // The booked time inside this hour, in seconds after midnight.
                    $from = max($booking->start_minute, $h * 60) * 60;
                    $to = min($booking->end_minute, $h * 60 + 60) * 60;
                    foreach ($this->loops->positions($screen, $day, $h)[$booking->getKey()] ?? [] as $slot) {
                        if ($slot + $booking->slot_count > $screen->slotCount()) {
                            continue; // overbooked by hand: never send a place past the loop's end
                        }
                        $key = "{$booking->creative->uuid}|{$slot}|{$booking->slot_count}";
                        $items[$key] ??= [
                            'creative_id' => $booking->creative->uuid,
                            'url' => "/device/v1/media/{$booking->creative->uuid}",
                            'sha256' => $booking->creative->sha256,
                            'duration_s' => $screen->spot_seconds * $booking->slot_count,
                            'slot_index' => $slot,
                            'slot_count' => $booking->slot_count,
                            'windows' => [],
                        ];
                        $offset = $slot * $screen->spot_seconds;
                        $items[$key]['windows'][] = [$midnight + $from + $offset, $midnight + $to + $offset];
                    }
                }
            }
        }
        // Back-to-back hours at the same place become one window.
        $items = array_values(array_map(function (array $item) {
            sort($item['windows']);
            $merged = [];
            foreach ($item['windows'] as $w) {
                if ($merged !== [] && $merged[count($merged) - 1][1] === $w[0]) {
                    $merged[count($merged) - 1][1] = $w[1];
                } else {
                    $merged[] = $w;
                }
            }
            $item['windows'] = $merged;

            return $item;
        }, $items));
        usort($items, fn ($a, $b) => [$a['slot_index'], $a['windows'][0][0]] <=> [$b['slot_index'], $b['windows'][0][0]]);

        $content = [
            'tz_offset_s' => $now->setTimezone($tz)->getOffset(),
            'loop_seconds' => $screen->loop_seconds,
            'spot_seconds' => $screen->spot_seconds,
            'items' => $items,
        ];

        return [$content, CarbonImmutable::parse($tomorrow->addDay()->toDateString(), $tz)->getTimestamp()];
    }

    /** @return array{payload: string, signature: string}|null */
    public function forDevice(Device $device): ?array
    {
        $manifest = $this->manifests->forScreen($device->screen);

        return $manifest ? ['payload' => $manifest->payload, 'signature' => $manifest->signature] : null;
    }

    /**
     * An ad file the device may download: only ads booked on its own screen.
     *
     * @return array{path: string, mime: string}
     */
    public function media(Device $device, string $creativeUuid): array
    {
        $creative = $this->manifests->playableCreative($device->screen, $creativeUuid)
            ?? throw new ModelNotFoundException;

        return ['path' => Storage::disk('local')->path($creative->processed_path), 'mime' => $creative->mime];
    }
}
