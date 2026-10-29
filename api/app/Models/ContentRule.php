<?php

namespace App\Models;

use App\Enums\ContentRule as Rule;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rule'])]
class ContentRule extends Model
{
    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    protected function casts(): array
    {
        return ['rule' => Rule::class];
    }
}
