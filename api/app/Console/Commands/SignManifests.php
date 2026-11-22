<?php

namespace App\Console\Commands;

use App\Services\ManifestService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('manifests:sign')]
#[Description('Sign a new playlist for every connected screen whose bookings changed or whose playlist is about to expire.')]
class SignManifests extends Command
{
    public function handle(ManifestService $manifests): int
    {
        $signed = $manifests->signDue(CarbonImmutable::now());
        $this->info("{$signed} manifests signed.");

        return self::SUCCESS;
    }
}
