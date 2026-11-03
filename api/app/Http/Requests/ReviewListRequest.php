<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewListRequest extends FormRequest
{
    public function rules(): array
    {
        return ['status' => ['sometimes', 'string', 'max:20']];
    }
}
