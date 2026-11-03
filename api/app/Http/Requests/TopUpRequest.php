<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TopUpRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:'.config('dooh.wallet.min_topup'), 'max:'.config('dooh.wallet.max_topup')],
            'gateway' => ['required', Rule::in(config('dooh.wallet.gateways'))],
        ];
    }
}
