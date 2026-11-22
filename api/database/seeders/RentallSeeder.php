<?php

namespace Database\Seeders;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * RentALL Nepal's own account for the lab: it owns billboards (one of them driven by the Raspberry
 * Pi) and can also book ads. Safe to run again; it only fills in what is missing.
 *
 *   php artisan db:seed --class=RentallSeeder
 *
 * Signs in with email and password where LAB_LOCAL_LOGIN=true. Change the password before real use.
 */
class RentallSeeder extends Seeder
{
    public const EMAIL = 'rentalnepal@screen.com';

    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $user = User::firstOrCreate(['email' => self::EMAIL], ['name' => 'RentALL Nepal', 'password' => self::EMAIL]);
        $user->assignRole('owner', 'advertiser');

        if (! $user->termsAcceptances()->where('version', config('terms.version'))->exists()) {
            $user->termsAcceptances()->create([
                'version' => config('terms.version'), 'text_sha256' => hash('sha256', (string) config('terms.version')), 'accepted_at' => now(),
            ]);
        }
        if (! $user->identityDocuments()->where('status', 'approved')->exists()) {
            $user->identityDocuments()->create([
                'type' => 'company_registration', 'number' => 'RENTALL-LAB', 'mime' => 'image/jpeg', 'sha256' => str_repeat('0', 64), 'status' => 'approved',
            ]);
        }

        $owner = $this->org($user, 'RentALL Nepal', 'owner');
        $this->org($user, 'RentALL Nepal Ads', 'advertiser');

        $common = [
            'width_px' => 1920, 'height_px' => 1080, 'orientation' => 'landscape',
            'opens_at' => '06:00', 'closes_at' => '22:00', 'loop_seconds' => 120, 'spot_seconds' => 10,
            'price_per_play' => 4, 'status' => 'active',
        ];
        $this->screen($owner, 'RentALL Office Screen (Raspberry Pi)', $common + [
            'address' => 'RentALL Nepal office, Kathmandu', 'lat' => 27.7090, 'lng' => 85.3200,
            'description' => 'The lab screen: a TV driven by a Raspberry Pi over HDMI.',
            'audience_notes' => 'Visitors and staff at the RentALL office.',
        ]);
        $this->screen($owner, 'RentALL Showroom Billboard', $common + [
            'address' => 'RentALL showroom, Lalitpur', 'lat' => 27.6780, 'lng' => 85.3180,
            'description' => 'Street-facing digital billboard outside the RentALL showroom.',
            'audience_notes' => 'Shoppers and passing traffic.',
        ]);
    }

    private function org(User $user, string $name, string $type): Organisation
    {
        $org = Organisation::query()->where('name', $name)->where('type', $type)->first()
            ?? Organisation::create(['name' => $name, 'type' => $type]);
        if (! $org->verified_at) {
            $org->forceFill(['verified_at' => now()])->save();
        }
        Membership::firstOrCreate(
            ['user_id' => $user->getKey(), 'organisation_id' => $org->getKey()],
            ['role' => MembershipRole::Admin],
        );

        return $org;
    }

    private function screen(Organisation $org, string $name, array $attributes): Screen
    {
        return $org->screens()->firstOrCreate(['name' => $name], $attributes);
    }
}
