<?php

namespace App\Services\Devices;

use RuntimeException;

/** Ed25519 signatures over manifest payload bytes (libsodium). */
class ManifestSigner
{
    private ?string $secretKey = null;

    /** @return string base64 detached signature */
    public function sign(string $bytes): string
    {
        return base64_encode(sodium_crypto_sign_detached($bytes, $this->secretKey()));
    }

    /** @return string base64 public key, the value devices verify against */
    public function publicKey(): string
    {
        $configured = config('dooh.manifest.public_key');
        if ($configured) {
            return $configured;
        }
        if (! is_readable((string) config('dooh.manifest.signing_key_path'))) {
            throw new RuntimeException('Set MANIFEST_SIGNING_PUBKEY (run: php artisan dooh:manifest-key).');
        }

        return base64_encode(sodium_crypto_sign_publickey_from_secretkey($this->secretKey()));
    }

    /** A new random seed in the format the key file holds. */
    public static function newSeed(): string
    {
        return base64_encode(random_bytes(SODIUM_CRYPTO_SIGN_SEEDBYTES));
    }

    public static function publicKeyFromSeed(string $seed): string
    {
        return base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair(self::decodeSeed($seed))));
    }

    private function secretKey(): string
    {
        if ($this->secretKey === null) {
            $path = (string) config('dooh.manifest.signing_key_path');
            $seed = is_readable($path) ? file_get_contents($path) : false;
            if ($seed === false) {
                throw new RuntimeException("Manifest signing key not readable: {$path} (MANIFEST_SIGNING_KEY_PATH).");
            }
            $this->secretKey = sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair(self::decodeSeed($seed)));
        }

        return $this->secretKey;
    }

    private static function decodeSeed(string $seed): string
    {
        $raw = base64_decode(trim($seed), true);
        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SIGN_SEEDBYTES) {
            throw new RuntimeException('The manifest signing key must be 32 random bytes, base64 encoded.');
        }

        return $raw;
    }
}
