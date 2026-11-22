<?php

namespace App\Services;

use App\Enums\OrganisationType;
use App\Exceptions\DomainError;
use App\Models\Device;
use App\Models\User;
use App\Repositories\DeviceRepository;
use App\Services\Devices\CertificateAuthority;
use App\Services\Devices\ManifestSigner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Screen players. An owner gets a one-time code for a screen; the device makes its own key pair,
 * sends a CSR with the code and gets a client certificate back. Every device request is then
 * identified by that certificate (checked by Nginx, matched here), and refused once revoked.
 */
class DeviceService
{
    /** No 0/O or 1/I, so a code read off a screen can't be mistyped that way. */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** Update last_seen_at at most this often (devices poll every minute). */
    private const TOUCH_SECONDS = 30;

    public function __construct(
        private DeviceRepository $devices,
        private ScreenService $screens,
        private OrganisationService $orgs,
        private CertificateAuthority $ca,
        private ManifestSigner $signer,
    ) {}

    public function forScreen(User $user, string $orgUuid, string $screenUuid): ?Device
    {
        return $this->devices->activeForScreen($this->screens->ownerScreen($user, $orgUuid, $screenUuid));
    }

    /**
     * A new enrolment code for the screen (any earlier unused code stops working).
     *
     * @return array{code: string, expires_at: CarbonImmutable}
     */
    public function issueCode(User $user, string $orgUuid, string $screenUuid): array
    {
        $this->orgs->membership($user, $orgUuid, OrganisationType::Owner, write: true);
        $screen = $this->screens->ownerScreen($user, $orgUuid, $screenUuid);

        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }
        $expiresAt = CarbonImmutable::now()->addMinutes((int) config('dooh.devices.code_ttl_minutes'));
        $this->devices->replaceCode($screen, $user, hash('sha256', $code), $expiresAt);

        return ['code' => substr($code, 0, 4).'-'.substr($code, 4), 'expires_at' => $expiresAt];
    }

    /** Disconnect the screen's device: its certificate is refused from the next request on. */
    public function revoke(User $user, string $orgUuid, string $screenUuid): void
    {
        $this->orgs->membership($user, $orgUuid, OrganisationType::Owner, write: true);
        $screen = $this->screens->ownerScreen($user, $orgUuid, $screenUuid);

        if ($this->devices->revokeActive($screen, CarbonImmutable::now()) === 0) {
            throw DomainError::conflict('No device is connected to this screen.');
        }
    }

    /**
     * Spend a code: sign the device's CSR and make it the screen's only device.
     *
     * @return array{device: Device, certificate: string, ca_certificate: string, signing_public_key: string}
     */
    public function enrol(string $code, string $csrPem): array
    {
        $sha256 = hash('sha256', strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $code)));

        return DB::transaction(function () use ($sha256, $csrPem) {
            $now = CarbonImmutable::now();
            $row = $this->devices->lockUsableCode($sha256, $now)
                ?? throw new DomainError('That code is wrong or has expired. Get a new one in the portal.', 422, ['code' => ['Invalid or expired code.']]);

            $cert = $this->ca->signDeviceRequest($csrPem);
            $this->devices->markUsed($row, $now);
            $this->devices->revokeActive($row->screen, $now);
            $device = $this->devices->create([
                'screen_id' => $row->screen_id,
                'cert_serial' => $cert['serial'],
                'cert_fingerprint' => $cert['fingerprint'],
                'cert_expires_at' => $cert['expires_at'],
                'enrolled_by' => $row->created_by,
                'enrolled_at' => $now,
            ]);

            return [
                'device' => $device->setRelation('screen', $row->screen),
                'certificate' => $cert['pem'],
                'ca_certificate' => $this->ca->certificate(),
                'signing_public_key' => $this->signer->publicKey(),
            ];
        });
    }

    /**
     * The device behind a request, from the certificate fingerprint Nginx passes after it verified
     * the certificate against the device CA.
     */
    public function authenticate(?string $fingerprint, ?string $ip, ?string $playerVersion): Device
    {
        $fingerprint = strtolower(trim((string) $fingerprint));
        if (! preg_match('/^[0-9a-f]{40}$/', $fingerprint)) {
            throw DomainError::unauthenticated('A device certificate is required.');
        }
        $device = $this->devices->findActiveByFingerprint($fingerprint)
            ?? throw DomainError::forbidden('This device is not connected to a screen. Enrol it again.');

        $now = CarbonImmutable::now();
        $playerVersion = $playerVersion !== null ? substr($playerVersion, 0, 40) : null;
        if (! $device->last_seen_at || $device->last_seen_at->lt($now->subSeconds(self::TOUCH_SECONDS))
            || $device->last_ip !== $ip || $device->player_version !== $playerVersion) {
            $this->devices->touch($device, $now, $ip, $playerVersion);
        }

        return $device;
    }
}
