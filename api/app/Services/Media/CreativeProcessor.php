<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Turns an uploaded ad into the only file screens ever get: re-encoded at exactly the chosen
 * resolution, metadata stripped (no GPS, camera or author data), and for video no sound.
 * Anything that isn't really an image or video fails here instead of reaching a screen.
 */
class CreativeProcessor
{
    /**
     * @return array{ok: bool, errors: list<string>, notes: list<string>, source?: array, processed?: string, thumb?: string, mime?: string, duration?: float|null}
     */
    public function process(string $input, string $kind, int $width, int $height, string $outDir): array
    {
        if (! is_dir($outDir) && ! mkdir($outDir, 0775, true) && ! is_dir($outDir)) {
            throw new RuntimeException("Cannot create {$outDir}");
        }

        return $kind === 'video'
            ? $this->video($input, $width, $height, $outDir)
            : $this->image($input, $width, $height, $outDir);
    }

    private function image(string $input, int $w, int $h, string $outDir): array
    {
        $info = @getimagesize($input);
        $src = $info ? @imagecreatefromstring((string) file_get_contents($input)) : false;
        if (! $info || ! $src) {
            return $this->fail('The file is not a readable image.');
        }
        [$iw, $ih] = [$info[0], $info[1]];
        if ($error = $this->shapeError($iw, $ih, $w, $h)) {
            return $this->fail($error, ['width' => $iw, 'height' => $ih]);
        }

        $dst = imagecreatetruecolor($w, $h);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $iw, $ih);
        $processed = $outDir.'/ad.webp';
        imagewebp($dst, $processed, 90);

        $tw = $w >= $h ? 480 : (int) round(480 * $w / $h);
        $th = (int) round($tw * $h / $w);
        $thumb = imagecreatetruecolor($tw, $th);
        imagecopyresampled($thumb, $dst, 0, 0, 0, 0, $tw, $th, $w, $h);
        imagejpeg($thumb, $outDir.'/thumb.jpg', 82);

        $notes = ['Re-encoded to WebP at '.$w.'×'.$h.'; hidden metadata removed.'];
        if ($iw !== $w) {
            $notes[] = "Scaled from {$iw}×{$ih}.";
        }

        return ['ok' => true, 'errors' => [], 'notes' => $notes, 'source' => ['width' => $iw, 'height' => $ih],
            'processed' => $processed, 'thumb' => $outDir.'/thumb.jpg', 'mime' => 'image/webp', 'duration' => null];
    }

    private function video(string $input, int $w, int $h, string $outDir): array
    {
        $probe = Process::timeout(60)->run(['ffprobe', '-v', 'error', '-print_format', 'json', '-show_streams', '-show_format', $input]);
        $data = json_decode($probe->output(), true);
        $stream = collect($data['streams'] ?? [])->firstWhere('codec_type', 'video');
        if (! $probe->successful() || ! $stream) {
            return $this->fail('The file is not a playable video.');
        }

        [$vw, $vh] = [(int) $stream['width'], (int) $stream['height']];
        // Phone videos are often stored sideways with a rotation flag.
        $rotation = abs((int) ($stream['tags']['rotate'] ?? collect($stream['side_data_list'] ?? [])->firstWhere('rotation')['rotation'] ?? 0));
        if ($rotation === 90 || $rotation === 270) {
            [$vw, $vh] = [$vh, $vw];
        }
        $duration = (float) ($data['format']['duration'] ?? $stream['duration'] ?? 0);
        $max = (int) config('dooh.creatives.max_video_seconds');

        $errors = [];
        if ($duration <= 0.5) {
            $errors[] = 'The video is empty or too short.';
        } elseif ($duration > $max) {
            $errors[] = 'The video is '.round($duration).' s long; the most allowed is '.$max.' s.';
        }
        if ($error = $this->shapeError($vw, $vh, $w, $h)) {
            $errors[] = $error;
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'notes' => [], 'source' => ['width' => $vw, 'height' => $vh, 'duration' => $duration]];
        }

        $processed = $outDir.'/ad.mp4';
        $encode = Process::timeout(900)->run([
            'ffmpeg', '-y', '-v', 'error', '-i', $input, '-map', '0:v:0',
            '-vf', "scale={$w}:{$h}:force_original_aspect_ratio=increase,crop={$w}:{$h},fps=30",
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p',
            '-an', '-map_metadata', '-1', '-movflags', '+faststart', $processed,
        ]);
        if (! $encode->successful() || ! is_file($processed)) {
            return $this->fail('We could not convert this video. Try exporting it again as MP4 (H.264).');
        }

        $at = number_format(min(1.0, $duration / 2), 2, '.', '');
        $thumbW = $w >= $h ? 480 : -2;
        $thumbH = $w >= $h ? -2 : 480;
        Process::timeout(60)->run(['ffmpeg', '-y', '-v', 'error', '-ss', $at, '-i', $processed, '-frames:v', '1', '-vf', "scale={$thumbW}:{$thumbH}", $outDir.'/thumb.jpg']);

        return ['ok' => true, 'errors' => [], 'source' => ['width' => $vw, 'height' => $vh, 'duration' => $duration],
            'notes' => ["Re-encoded to H.264 MP4 at {$w}×{$h}, 30 fps; sound and hidden metadata removed."],
            'processed' => $processed, 'thumb' => is_file($outDir.'/thumb.jpg') ? $outDir.'/thumb.jpg' : null,
            'mime' => 'video/mp4', 'duration' => round($duration, 2)];
    }

    /** The upload must have the same shape as the chosen resolution and be at least half its size. */
    private function shapeError(int $iw, int $ih, int $w, int $h): ?string
    {
        if ($iw <= 0 || $ih <= 0) {
            return 'The file has no size.';
        }
        if (abs($iw / $ih - $w / $h) > 0.02) {
            return "Your file is {$iw}×{$ih}, a different shape from {$w}×{$h}. Crop it to the same shape and upload again.";
        }
        if ($iw < $w / 2) {
            return "Your file is {$iw}×{$ih}, too small for {$w}×{$h}. Use at least ".intdiv($w, 2).'×'.intdiv($h, 2).'.';
        }

        return null;
    }

    private function fail(string $error, array $source = []): array
    {
        return ['ok' => false, 'errors' => [$error], 'notes' => [], 'source' => $source];
    }
}
