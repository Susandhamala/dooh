<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnrolDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'csr' => ['required', 'string', 'max:4000', 'starts_with:-----BEGIN CERTIFICATE REQUEST-----'],
        ];
    }
}
