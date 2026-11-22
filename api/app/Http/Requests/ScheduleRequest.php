<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleRequest extends FormRequest
{
    public function rules(): array
    {
        return ['date' => ['sometimes', 'date_format:Y-m-d']];
    }
}
