<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DecideBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'note' => ['sometimes', 'nullable', 'string', 'max:300'],
            // The day the owner is looking at, so the reply can be that day's schedule.
            'date' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }
}
