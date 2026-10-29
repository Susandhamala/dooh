<?php

namespace App\Http\Requests;

use App\Enums\OrganisationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganisationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(OrganisationType::class)],
            'pan_number' => ['nullable', 'string', 'regex:/^[0-9]{9}$/'],
        ];
    }
}
