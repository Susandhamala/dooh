<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class MfaCodeRequest extends FormRequest
{
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'digits:6']];
    }
}
