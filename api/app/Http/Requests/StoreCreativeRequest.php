<?php

namespace App\Http\Requests;

use App\Enums\CreativeCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreativeRequest extends FormRequest
{
    public function rules(): array
    {
        $resolutions = array_map(fn ($r) => $r['width'].'x'.$r['height'], config('dooh.creatives.resolutions'));
        $isVideo = str_starts_with((string) $this->file('file')?->getMimeType(), 'video/');
        $maxKb = ($isVideo ? config('dooh.creatives.max_video_mb') : config('dooh.creatives.max_image_mb')) * 1024;

        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'category' => ['required', Rule::enum(CreativeCategory::class)],
            'resolution' => ['required', Rule::in($resolutions)],
            'file' => ['required', 'file', 'max:'.$maxKb, 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm'],
            // The per-upload content declaration (on top of the accepted terms).
            'declaration' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimetypes' => 'Upload a JPG, PNG or WebP image, or an MP4, MOV or WebM video.',
            'file.max' => 'The file is too large.',
            'declaration.accepted' => 'Confirm that your ad follows the content policy.',
        ];
    }
}
