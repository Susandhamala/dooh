<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SsoExchangeRequest extends FormRequest
{
    public function rules(): array
    {
        // 32 random bytes, hex or base64url encoded by rentallnepal.
        return ['code' => ['required', 'string', 'min:32', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/']];
    }
}
