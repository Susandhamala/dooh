<?php

namespace App\Http\Requests\Auth;

use App\Rules\NepaliMobile;
use Illuminate\Foundation\Http\FormRequest;

class PhoneCodeRequest extends FormRequest
{
    public function rules(): array
    {
        return ['phone' => ['required', 'string', 'max:20', new NepaliMobile]];
    }
}
