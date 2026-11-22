<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'direction' => ['required', 'in:up,down'],
            'date' => ['required', 'date_format:Y-m-d'],
            'hour' => ['required', 'integer', 'between:0,23'],
        ];
    }
}
