<?php

namespace Database\Factories;

use App\Enums\MembershipRole;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Organisation> */
class OrganisationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'type' => 'advertiser',
        ];
    }

    public function owner(): static
    {
        return $this->state(['type' => 'owner']);
    }

    public function verified(): static
    {
        return $this->afterMaking(fn (Organisation $org) => $org->verified_at = now());
    }

    public function withMember(User $user, MembershipRole $role = MembershipRole::Admin): static
    {
        return $this->afterCreating(function (Organisation $org) use ($user, $role) {
            $org->members()->attach($user, ['role' => $role->value]);
            $user->assignRole($org->type->platformRole());
        });
    }
}
