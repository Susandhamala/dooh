<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScreenCatalogueRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'orientation' => ['sometimes', 'in:landscape,portrait'],
            // minLng,minLat,maxLng,maxLat — the visible map area.
            'bbox' => ['sometimes', 'string', 'regex:/^-?\d+(\.\d+)?(,-?\d+(\.\d+)?){3}$/'],
        ];
    }

    public function filters(): array
    {
        $filters = $this->safe()->only(['orientation']);
        if ($this->filled('bbox')) {
            $filters['bbox'] = array_map('floatval', explode(',', $this->input('bbox')));
        }

        return $filters;
    }
}
