<?php

namespace App\Models;

use App\Enums\OrganisationType;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'pan_number'])]
#[Hidden(['id'])]
class Organisation extends Model
{
    use HasFactory, HasPublicUuid;

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'memberships')->withPivot('role')->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function screens(): HasMany
    {
        return $this->hasMany(Screen::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    protected function casts(): array
    {
        return [
            'type' => OrganisationType::class,
            'verified_at' => 'datetime',
        ];
    }
}
