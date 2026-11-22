<?php

namespace App\Services\Devices;

use App\Exceptions\DomainError;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Signs device certificate requests with the device CA. The device made its key pair itself and
 * only sends the public half (in the CSR), so its private key never leaves it.
 */
class CertificateAuthority
{
    /**
     * @return array{pem: string, fingerprint: string, serial: string, expires_at: CarbonImmutable}
     */
    public function signDeviceRequest(string $csrPem): array
    {
        $key = @openssl_csr_get_public_key($csrPem);
        if ($key === false || (openssl_pkey_get_details($key)['type'] ?? null) !== OPENSSL_KEYTYPE_ED25519) {
            throw new DomainError('The device sent an invalid request.', 422, ['csr' => ['Expected an Ed25519 certificate signing request.']]);
        }

        // Ed25519 signs the whole certificate itself, so no separate digest ("null").
        $cert = @openssl_csr_sign($csrPem, $this->read('ca_cert_path'), $this->read('ca_key_path'), (int) config('dooh.devices.cert_days'), [
            'config' => resource_path('openssl/device-cert.cnf'),
            'x509_extensions' => 'device_cert',
            'digest_alg' => 'null',
        ], random_int(1, PHP_INT_MAX));
        if ($cert === false) {
            // A CSR whose own signature doesn't verify ends up here too.
            throw new DomainError('The device sent an invalid request.', 422, ['csr' => ['The request could not be signed.']]);
        }

        openssl_x509_export($cert, $pem);
        $info = openssl_x509_parse($cert);

        return [
            'pem' => $pem,
            'fingerprint' => openssl_x509_fingerprint($cert, 'sha1'),
            'serial' => strtolower($info['serialNumberHex']),
            'expires_at' => CarbonImmutable::createFromTimestamp($info['validTo_time_t']),
        ];
    }

    public function certificate(): string
    {
        return $this->read('ca_cert_path');
    }

    private function read(string $setting): string
    {
        $path = (string) config("dooh.devices.{$setting}");
        $contents = is_readable($path) ? file_get_contents($path) : false;

        return $contents ?: throw new RuntimeException("Device CA file not readable: {$path} (dooh.devices.{$setting}).");
    }
}
