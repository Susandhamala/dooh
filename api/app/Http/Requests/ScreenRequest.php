<?php

namespace App\Http\Requests;

use App\Enums\ContentRule;
use App\Enums\ScreenStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Create (POST) requires the core fields; update (PATCH) accepts any subset. */
class ScreenRequest extends FormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            // Roughly Nepal's bounding box.
            'lat' => [$required, 'numeric', 'between:26.3,30.5'],
            'lng' => [$required, 'numeric', 'between:80.0,88.3'],
            'width_px' => [$required, 'integer', 'between:320,7680'],
            'height_px' => [$required, 'integer', 'between:320,7680'],
            'orientation' => [$required, Rule::in(['landscape', 'portrait'])],
            'opens_at' => ['sometimes', 'date_format:H:i'],
            'closes_at' => ['sometimes', 'date_format:H:i'],
            'loop_seconds' => ['sometimes', 'integer', 'between:10,3600'],
            'spot_seconds' => ['sometimes', 'integer', 'between:5,120'],
            'slot_price' => [$required, 'numeric', 'between:1,10000000'],
            'audience_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::enum(ScreenStatus::class)],
            'content_rules' => ['sometimes', 'array'],
            'content_rules.*' => [Rule::enum(ContentRule::class)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $loop = (int) $this->input('loop_seconds', 120);
            $spot = (int) $this->input('spot_seconds', 10);
            if ($spot > 0 && ($loop < $spot || $loop % $spot !== 0)) {
                $v->errors()->add('loop_seconds', 'Loop length must be a whole multiple of the spot length.');
            }
        }];
    }
}
