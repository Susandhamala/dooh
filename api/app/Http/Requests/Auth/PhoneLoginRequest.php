<?php

namespace App\Http\Requests\Auth;

use App\Rules\NepaliMobile;
use Illuminate\Foundation\Http\FormRequest;

class PhoneLoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20', new NepaliMobile],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
            // Only needed the first time a number signs in.
            'name' => ['nullable', 'string', 'min:2', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return ['code.regex' => 'Enter the 6-digit code from the SMS.'];
    }
}
