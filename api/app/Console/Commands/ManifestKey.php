<?php

namespace App\Console\Commands;

use App\Services\Devices\ManifestSigner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('dooh:manifest-key {--generate : Create the key file if it does not exist yet}')]
#[Description('Print the manifest signing public key (MANIFEST_SIGNING_PUBKEY), optionally creating the private key first.')]
class ManifestKey extends Command
{
    public function handle(): int
    {
        $path = (string) config('dooh.manifest.signing_key_path');
        if ($path === '') {
            $this->error('Set MANIFEST_SIGNING_KEY_PATH first.');

            return self::FAILURE;
        }

        if (! file_exists($path)) {
            if (! $this->option('generate')) {
                $this->error("No key at {$path}. Run again with --generate to create one.");

                return self::FAILURE;
            }
            $old = umask(0077);
            file_put_contents($path, ManifestSigner::newSeed()."\n");
            umask($old);
            $this->info("Created {$path}. Keep it secret: anyone with it can sign playlists.");
        }

        $this->line('MANIFEST_SIGNING_PUBKEY='.ManifestSigner::publicKeyFromSeed((string) file_get_contents($path)));

        return self::SUCCESS;
    }
}
