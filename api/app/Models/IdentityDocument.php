<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\ReviewStatus;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'type', 'number', 'file_path', 'mime', 'sha256', 'status', 'reviewed_by', 'reviewed_at', 'review_note'])]
#[Hidden(['id', 'user_id', 'number', 'file_path', 'reviewed_by'])]
class IdentityDocument extends Model
{
    use HasPublicUuid;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Last four characters only; the full number never leaves the server after upload. */
    public function maskedNumber(): string
    {
        $n = (string) $this->number;

        return str_repeat('•', max(0, mb_strlen($n) - 4)).mb_substr($n, -4);
    }

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => ReviewStatus::class,
            'number' => 'encrypted',
            'reviewed_at' => 'immutable_datetime',
        ];
    }
}
