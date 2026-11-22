<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string', 'max:20'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
