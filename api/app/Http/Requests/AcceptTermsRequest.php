<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptTermsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'max:40'],
            'agree' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return ['agree.accepted' => 'Tick the box to agree to the terms and content policy.'];
    }
}
