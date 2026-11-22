<?php

namespace App\Http\Requests;

use App\Services\BookingService;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'screen_id' => ['required', 'uuid'],
            'creative_id' => ['required', 'uuid'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            // Nepal time, to the minute; "24:00" is midnight at the end of the day.
            'start_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'end_time' => ['required', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00)$/'],
            // How many times the ad plays in every loop (1 when left out).
            'repeats' => ['sometimes', 'integer', 'between:1,'.BookingService::MAX_REPEATS],
        ];
    }

    public function messages(): array
    {
        return [
            'start_time.regex' => 'Give the start time as HH:MM.',
            'end_time.regex' => 'Give the end time as HH:MM.',
        ];
    }
}
