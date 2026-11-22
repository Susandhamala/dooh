<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class AdminLoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:200'],
        ];
    }
}
