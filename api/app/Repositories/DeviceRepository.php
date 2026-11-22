<?php

namespace App\Repositories;

use App\Models\Device;
use App\Models\DeviceEnrolmentCode;
use App\Models\Screen;
use App\Models\User;
use Carbon\CarbonImmutable;

class DeviceRepository
{
    public function activeForScreen(Screen $screen): ?Device
    {
        return $screen->activeDevice()->first();
    }

    /** The enrolled, not revoked device holding this certificate. */
    public function findActiveByFingerprint(string $fingerprint): ?Device
    {
        return Device::with('screen')->where('cert_fingerprint', $fingerprint)->whereNull('revoked_at')->first();
    }

    public function create(array $attributes): Device
    {
        return Device::create($attributes);
    }

    public function revokeActive(Screen $screen, CarbonImmutable $at): int
    {
        return Device::where('screen_id', $screen->getKey())->whereNull('revoked_at')->update(['revoked_at' => $at, 'updated_at' => $at]);
    }

    public function touch(Device $device, CarbonImmutable $at, ?string $ip, ?string $version): void
    {
        $device->forceFill(['last_seen_at' => $at, 'last_ip' => $ip, 'player_version' => $version])->save();
    }

    /** Replace any unused code for $screen with a new one. */
    public function replaceCode(Screen $screen, User $by, string $sha256, CarbonImmutable $expiresAt): DeviceEnrolmentCode
    {
        DeviceEnrolmentCode::where('screen_id', $screen->getKey())->whereNull('used_at')->delete();

        return DeviceEnrolmentCode::create([
            'screen_id' => $screen->getKey(), 'code_sha256' => $sha256, 'created_by' => $by->getKey(),
            'expires_at' => $expiresAt, 'created_at' => now(),
        ]);
    }

    /** An unused, unexpired code, locked so two devices can't spend it at once. */
    public function lockUsableCode(string $sha256, CarbonImmutable $now): ?DeviceEnrolmentCode
    {
        return DeviceEnrolmentCode::with('screen')
            ->where('code_sha256', $sha256)->whereNull('used_at')->where('expires_at', '>', $now)
            ->lockForUpdate()->first();
    }

    public function markUsed(DeviceEnrolmentCode $code, CarbonImmutable $at): void
    {
        $code->forceFill(['used_at' => $at])->save();
    }
}
