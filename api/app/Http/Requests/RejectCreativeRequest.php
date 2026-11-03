<?php

namespace App\Http\Requests;

use App\Enums\RejectReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RejectCreativeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(RejectReason::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
