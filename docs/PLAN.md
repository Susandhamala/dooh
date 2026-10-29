# DOOH Ad Screen System Plan

Oct 29, 2026 · @rentall

## Summary

A self-service ad screen marketplace at **ads.rentallnepal.com**, separate from the main
rentallnepal.com app but on the same VPS. Advertisers book screen time and publish ads in three
steps (**Create, Check, Upload**); screen owners list screens and approve every campaign. Screens
only play content the platform has signed, and only talk to the platform over mutual TLS.

### Goals for the first working version

- An advertiser can sign up, book a slot on a screen, upload an ad and see it play, with no phone calls in between.
- A screen owner approves or rejects each ad with a reason, and the advertiser can appeal.
- No unsigned or unapproved content can reach a screen, even if someone gets into the network or steals a device.
- Every publish, approval and playback is recorded in a tamper-evident log.

## MVP scope: thesis first, rentallnepal feature second

Own app at ads.rentallnepal.com, reached from a "Screen Ads" link in rentallnepal.com, signed in
with a rentallnepal account. In code it stays separate so the thesis can be tested and attacked in
the Docker lab without touching the live site.

### Login with rentallnepal (one-time code exchange)

rentallnepal.com stays the identity provider; the two apps never share a JWT secret or database.

1. User (logged in to rentallnepal) clicks "Screen Ads". The ads app sets a random `state` for the browser.
2. The Vue app calls `POST /api/v1/sso/ads/code` on rentallnepal (`auth:api`). It stores a random 32-byte code in Redis for 60 s, single use, bound to the user's UUID and the ads callback URL.
3. Browser goes to `https://ads.rentallnepal.com/sso/callback?code=...&state=...`; the ads app checks `state` (login CSRF).
4. The ads backend calls `POST /api/v1/sso/ads/exchange` server-to-server with a client secret and gets back only UUID, name, email, phone and verified flags.
5. The ads app upserts its local user (by `rentall_uuid`), issues its own short-lived JWT with its own secret, and requires TOTP MFA for owners and admins.

Disabled rentallnepal account → exchange refuses. A **lab-only local login** keeps thesis tests independent of the live site.

| In the MVP (must work for the thesis) | Mocked or simulated | Later, for the real feature |
|---|---|---|
| Login via rentallnepal + MFA, roles (advertiser, owner, admin) | Payment (fake "Pay" button) | eSewa or Khalti payments, payouts to owners |
| Screen list, simple slot booking | Identity documents (simulated check) | Real business verification |
| Create, Check (file checks, re-encode, owner approval, appeal), Upload | Most screens (Docker emulated players) | More screens, owner self-onboarding |
| Signed manifests, mutual TLS, device revocation | | Audience analytics, dynamic pricing |
| Proof-of-play log, hash-chained audit log | | Templates and HTML ads |
| One real Raspberry Pi screen for the demo | | |

## Users and modules

| Role | What they do |
|---|---|
| Advertiser | Verifies identity, browses screens, books slots, uploads ads, pays, sees proof-of-play reports |
| Screen owner | Registers screens, sets content rules and prices, approves or rejects campaigns, sees earnings |
| Platform admin | Verifies owners and advertisers, handles appeals and disputes, revokes devices, views audit log |
| Screen (device) | Fetches its signed playlist, plays it, sends back signed proof-of-play and health pings |

| Module | What it covers |
|---|---|
| Accounts and identity | Sign-up, email/phone OTP, TOTP MFA, simple business document check, role assignment |
| Screen catalogue | Location on a map, size, resolution, orientation, hours, audience notes, price per slot |
| Booking and scheduling | Slot calendar per screen, holds, conflicts, loop length (e.g. 10 s spots in a 2 min loop) |
| Create | Upload image or video, or template ad; preview at the screen's real resolution |
| Check | Automated file checks and re-encoding, policy flags, owner approval with written reason |
| Upload (delivery) | Build and sign a playlist manifest per screen, notify the device |
| Payments | Mock first; later eSewa/Khalti, refunds on rejection or offline screen |
| Device management | Enrol, issue/revoke certificates, health, remote screenshot, offline alerts |
| Reporting | Proof-of-play per campaign, uptime per screen, owner payouts |
| Audit and admin | Hash-chained audit log, appeals queue, user and screen suspension |

## Architecture

Own Docker Compose stack beside rentallnepal.com, sharing only host Nginx and the server. People
reach the portal over HTTPS; screens reach a separate device endpoint that Nginx only accepts with
a valid device certificate. Nothing in the ads stack can read the main app's database.

## Tech stack

| Layer | Choice | Notes |
|---|---|---|
| Ads API | PHP 8.4, Laravel 13; Route → Middleware → Controller → Form Request → Service → Repository → Model → Resource; `{success,message,data,errors,meta}` envelope | Arch tests enforce layering |
| Portal | Vue 3, Vite, Pinia, Vue Router, Tailwind 4, Axios via `src/lib/*`, Leaflet | rentallnepal design tokens |
| Auth and roles | jwt-auth (own secret), spatie/laravel-permission, TOTP (pragmarx/google2fa) | Login via code exchange |
| Database | PostgreSQL 17, separate DB and user, UUID public IDs | Exclusion constraint stops double bookings |
| Cache and queue | Redis 7, queue worker, scheduler | Re-encoding, manifest signing, expiring holds |
| Media | GD → WebP for images; FFmpeg via queued job for video; `finfo` true type | Players only get processed files |
| Signing | sodium Ed25519, key readable only by signing worker container | Screens verify every manifest |
| Device mTLS | Nginx `ssl_verify_client` against private CA; fingerprint passed to Laravel middleware | Revoked fingerprint refused at once |
| Audit log | spatie/laravel-activitylog + `prev_hash`/`hash` via observer | Edits to history detectable |
| Player agent | Python on device (mpv, requests/MQTT) | Docker copies emulate screens |
| Tests | Pest security tests: cross-account, unsigned/replayed manifest, revoked device, bad file types | Thesis evidence |
| Deploy | Separate Compose project + Makefile; host Nginx routes ads.rentallnepal.com; Let's Encrypt | Isolated from main app |

## Data model

| Table | Key fields | Notes |
|---|---|---|
| users | id, email, phone, password_hash, mfa_secret, status | One person, one login |
| organisations | id, name, type (advertiser/owner), pan_number, verified_at | Business identity |
| memberships | user_id, org_id, role | Role per org (admin, editor, viewer) |
| screens | id, owner_org_id, name, lat, lng, width_px, height_px, orientation, hours, loop_seconds, slot_price, status | What advertisers browse |
| content_rules | screen_id, rule, max_duration | Owner policy checked during Check |
| devices | id, screen_id, cert_serial, cert_fingerprint, enrolled_at, revoked_at, last_seen | One device per screen, revocable |
| campaigns | id, advertiser_org_id, name, start_date, end_date, status | Groups bookings and creatives |
| creatives | id, campaign_id, original_file, processed_file, sha256, mime, duration_s, check_result | Only processed files are played |
| bookings | id, campaign_id, screen_id, creative_id, date_range, spots_per_loop, status, price | Exclusion constraint |
| approvals | id, booking_id, reviewer_user_id, decision, reason, decided_at | Named human for every public ad |
| appeals | id, approval_id, message, outcome, decided_by | Right of appeal |
| manifests | id, screen_id, version, payload_json, signature, valid_from, expires_at | Version stops replays |
| play_logs | id, device_id, creative_id, played_at, duration_s, device_signature | Proof-of-play |
| audit_log | id, actor, action, target, created_at, prev_hash, hash | Hash chain |

Booking status: `draft → checking → awaiting_owner → approved → scheduled → live → completed`, with `rejected` and `cancelled` as exits.

## Screen player design

Keeps playing from local cache when the internet drops.

- **Hardware:** Raspberry Pi 5 (or Linux mini PC) on HDMI; UPS or clean shutdown.
- **Software:** locked-down kiosk Linux image; Python agent holds device key/cert, talks mTLS, downloads the signed manifest; verifies signature, version (strictly newer), expiry, and each file's SHA-256 before caching; plays via mpv (or Chromium kiosk for HTML); fallback loop of owner content; every play logged, signed, uploaded in batches; health ping every minute.
- **Enrolment:** owner gets a one-time code; device generates its key pair locally, sends a CSR with the code, receives its certificate. Private key never leaves the device. Lost device → admin revokes → API refuses at once.
- **Lab:** same agent in Docker containers emulating several screens.

## Security controls

| Threat | Control |
|---|---|
| Stolen or shared passwords | Per-person accounts, Argon2, TOTP for owners/admins, login rate limiting |
| Fake advertiser accounts | Email/phone OTP, PAN check, new accounts limited until first approval |
| BOLA | Every query scoped by organisation; cross-tenant tests on every endpoint |
| Malicious file upload | True type detection, size/resolution limits, full re-encode, originals never served |
| Offensive or misleading ads | Owner rules, automated flags, named human approval with reason; appeals to admin |
| Screen takeover / fake screen | mTLS per-device cert, revocation, no inbound ports on device |
| Injected or replayed playlist | Ed25519-signed manifests with version, expiry, file hashes |
| Tampering with history | Hash-chained audit log, daily anchor hash stored off the VPS |
| Attack on shared VPS | Own containers, own DB user; Nginx rate limits; firewall 80/443/SSH-key only |
| Signing key theft | Separate signing worker container; key readable only by it; rotation procedure |
| Personal data exposure | Data minimisation, ID docs encrypted at rest and deleted after verification (Individual Privacy Act 2075) |

Known limit: proof-of-play is device-signed, so a fully compromised device could fake it. Random remote screenshots and owner spot checks reduce but don't remove this.

## Roadmap

12 two-week sprints (24 weeks). Each phase ends with a demo gate. If time runs short, cut from
phase 4 (real payments, more screens), never from phase 2.

- **Phase 0** — Repo, Compose stack, CI, skeleton API/portal/player, lab CA.
- **Phase 1** — Accounts, SSO code exchange, MFA, roles, organisations; screen catalogue; booking with exclusion constraint.
- **Phase 2** — Create/Check/Upload: upload, file checks, re-encode, owner approval, appeals; signed manifests; device enrolment, mTLS, revocation; player verification.
- **Phase 3** — Proof-of-play, hash-chained audit log, reporting, security test suite, Raspberry Pi demo.
- **Phase 4** — Real payments, more screens, templates.

## Open questions

- [x] rentallnepal.com is Laravel 13 + Vue 3 + PostgreSQL 17; ads app uses the same stack.
- [ ] VPS size and provider? (Video re-encoding needs ≥2 cores, 4 GB RAM.)
- [ ] University project only, or real business? (Assumed: lab prototype first, designed for real use.)
- [ ] Any screens or screen owners lined up, and what hardware?
- [x] rentallnepal uses jwt-auth; ads app reuses it through the one-time code exchange.
- [ ] Pricing model: per slot, per day, or per 1,000 plays?
- [ ] Ad types: images and video only, or HTML templates and live content too?
