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

## Decisions, 30 Oct 2026

These change the plan above; the code follows them.

| Topic | Was | Now |
|---|---|---|
| Sign-in | rentallnepal SSO (+ lab email login) | Nepali mobile number (96x/97x/98x) + 6-digit SMS code. A new number gets an advertiser organisation. rentallnepal SSO stays as a second option. SMS goes to the log until a gateway (Sparrow / Aakash) is added. |
| Terms | Not covered | Everyone accepts a versioned terms and content policy (sexual content, violence, weapons, harassment, fraud, rights) before loading money, uploading or booking. Stored with version, text hash, time, IP and user agent. Each upload also needs a content declaration. |
| Identity | Simulated document check | Real upload (citizenship, national ID, passport, driving licence, company registration, PAN/VAT), encrypted at rest, opened only through a 10-minute signed link, deleted once decided. Booking needs a submitted document; approving an ad needs the uploader's document approved. |
| Who approves ads | Screen owner | The RentALL review team ("ad agency", admin role + two-factor) watches every ad and approves it or refuses it with a reason the advertiser sees. Owners' content rules are enforced automatically by ad category. |
| Pricing | Per slot per day, fake Pay button | Prepaid wallet per advertiser organisation. Screens have a price per play. Booking holds days × hours × plays per hour × price per play; each play is charged from the hold; the unplayed rest is released at the end, on cancel or on rejection. Top-ups are simulated (eSewa, Khalti, bank). |
| Booking | Whole days | Same hour window on each day of a date range; the database exclusion constraint covers screen, slot, dates and hours. |
| Campaigns | Required | Optional grouping; bookings belong to the organisation. |
| Proof of play | Device-signed logs | `play_logs` (one row per play, unique per booking and time, charged once). Until real players report, `plays:simulate` (lab, `SIMULATE_PLAYS=true`) records due plays every 10 minutes. |

## Decisions, 19 Nov 2026

| Topic | Was (30 Oct) | Now |
|---|---|---|
| Paying for a booking | The cost was held, each play charged from the hold | Paid in full from the wallet when booking. No holds. First to pay gets the slot (the exclusion constraint settles races). Plays that don't happen (cancelled, ad pulled, screen off) are refunded; plays only count, they don't charge. Migration `pay_first_bookings` turned existing holds into payments. |
| What can be booked | Ads still in review, by users with a pending document | Only approved ads, only by users whose identity document is approved. Loading money and uploading ads need a document submitted (`identity` middleware). |
| Admin | Review queues only | One admin panel: dashboard (to-dos, users, live bookings, money, 14-day chart), users (search, filter by verification, suspend / reinstate), identity documents, ads, all bookings (cancel with refund), organisations. Admins land on it after sign-in. |
| Terms | Version 2026-10-30 | Version 2026-11-19 (pay-first and verification wording), so everyone accepts again. |
| Admin sign-in | Phone code, then an authenticator code | Also `/4dmin`: username and password, straight into the admin panel (no authenticator step). Only accounts with the admin role can use it; passwords are hashed. Create one or change its password with `php artisan dooh:admin-login <username> --name=<name>` (asks for the password). Phone admins still need two-factor. |
| Home and finding screens | Separate landing page and /screens page | One home page: hero, the map and screen list (search, cheapest / most shows), 4-step "how it works", an animated picture of the screen loop (how often your ad shows), a price calculator, safety, plain-language questions, screen owners. `/screens` redirects to `/#find`. Signed-in users can open the home page too; "Dashboard" is first in their nav (the admin panel for admins). Animations respect reduced-motion. |
| Home page look | Drawn screen mock-up, text-heavy | Real photos (Unsplash License, credits in `portal/public/photos/CREDITS.md`). Hero: a real LED billboard photo with sample ads laid onto its screen by a perspective transform (`lib/perspective.ts`); the traffic lights in front are a cut-out layer. Visitors can switch ads or type their business name. Steps shown as small animated app pictures; Thamel street, Kathmandu shop and Kathmandu-at-night photos; short copy. |
| Home page polish | Photo sections, purple washes, many badges | Dark hero with grain and one light source, staggered entrance, billboard tilts toward the mouse, sliding ad switch, live ticker of real screens. Map full width with floating search; screens in a horizontal row below (hovering a card lights its pin). "Be seen" section removed. Calculator on a dark panel with rolling numbers. Numbered sections (01–05), lighter type weights, fewer pills. |
| Home page focus | Wishes and ads, dark hero | Light layout after the reference design, in plum and coral. Business ads first; the billboard opens on a RentALL Nepal ad (also Shop, Food, Trend, Yours). Personal use is one "Social trends" idea. Real billboard gallery: New York and Wenzhou photos with our ads laid onto their screens (`PhotoScreen.vue`, follows object-cover cropping), Akihabara as is. Map and screen cards on the home page before the ideas (`ScreenFinder.vue`, row). "Find screens" is its own page again (`/screens`, map plus card grid with shape filter). Nav: Find screens, How it works, FAQ. |
| Billboard photos | Foreign cities (New York, Wenzhou, Tokyo) | "Real billboards in Nepal" gallery: a digital billboard in Biratnagar (RentALL ad), a junction board in Janakpur (restaurant ad) and a street board in Biratnagar (college ad). Photos from Wikimedia Commons (CC BY-SA 4.0 / CC BY 3.0), credited on each photo, in the footer and in `public/photos/CREDITS.md`. The hero keeps the Wenzhou LED billboard. |
| Choosing an ad when booking | Only approved ads that fit the screen were listed; otherwise "None of your approved ads fit" | All approved ads (and ones still in review) are listed. The ones that can't play on this screen are greyed out with the exact reason: wrong shape, video longer than the screen's whole loop, or a category the screen doesn't show. Videos show their length on the thumbnail. The API still enforces the same rules. |
| Ad length | A video had to fit one slot (10–15 s) | Ads can be any length up to the screen's loop (60–120 s on the lab screens; uploads stop at 60 s). A longer video takes back-to-back slots, as many as it needs (34 s on 10 s slots = 4), and pays the play price for each. Bookings store `slot_index` + `slot_count`; the database guard compares slot ranges (`slot_range`). Availability also gives the longest free run per hour; the manifest sends `slot_count` and the player starts the video once and lets it run through its slots. |
| Booking planner | Hour buttons; server picked the slot | Visual planner: day strip (next 28 days, coloured by how busy the chosen time is), time-of-day bar with draggable handles in 5-minute steps (or exact times typed), and the loop drawn slot by slot to the second, with booked slots struck out, your ad on its slots and a live line for what is on screen now. Bookings are stored to the minute (`start_minute`/`end_minute`, guard on `minute_range`); the ad plays in every loop that starts inside the window (`PlaySchedule`, mirrored in `schedule.ts`). The advertiser may choose the slot (`slot_index`); `GET /screens/{id}/slots` shows which are taken. Before paying and in My bookings: "Your ad runs at 0:30–0:40 of every 2 min loop, slot is 10 s, plays at 08:00:30, 08:02:30 … 09:58:30, 60 a day". |
| Prices | रू 2 per play everywhere | Set from vendor figures (a prime Kathmandu digital board earns NPR 3–8 lakh a month shared by 6–8 advertisers; digital boards NPR 50,000–2,00,000 a month): New Road रू 4.50, Thamel रू 4, Durbar Marg रू 5 (15 s slot), Pulchowk रू 2.50, Koteshwor रू 6 (20 s slot) per play, about रू 1,900–3,200 for a full 16-hour day in one place. Owner form suggests रू 2.50–5 per 10 s play. |
| Packed loops | Each booking owned a fixed place in the loop (chosen by the advertiser), so gaps between ads were wasted | A booking reserves loop time (its slots × plays per loop) for its days and time; it has no fixed place. Every hour the screen packs the booked ads back to back (`LoopPacking`, oldest booking first, first copies before second copies), so gaps merge and any free slots can be booked. Capacity (booked ≤ slots per loop in every hour) is checked while holding a lock on the screen row, replacing the exclusion constraint. The same ad can play 1–3 times per loop (`repeats`) and can be booked again on the same screen. The planner is three steps (ad + plays per loop, days, time with available-time chips); the loop is shown as a read-only preview. Bookings show the loops they play in; the screen sets the exact second. |
| Production readiness | Lab-only setup: `api/.env` baked into the image, 5 PHP workers, no caching, Google Fonts, one shared rate limit behind a proxy | `docker-compose.prod.yml` + `make prod` (host Nginx with public TLS in front of 8080; screens on 8443). `.dockerignore` keeps secrets out of images. API image: OPcache, 16-worker FPM pool (12 in production), health checks, entrypoint that caches config/routes/events/views and migrates in production. Lab shortcuts (password login, codes on screen, simulated plays) are forced off when `APP_ENV=production`. Nginx: real visitor IP from the host proxy (per-visitor rate limits), gzip, 1-year cache for hashed assets, CSP (`script-src 'self'`), Permissions-Policy, HSTS over HTTPS, hidden version. Fonts self-hosted. Restart policies, log rotation, Redis persistence, `make backup` / `make restore`. Laravel and Symfony updated past published advisories (`composer audit` clean, `npm audit --omit=dev` clean). Guide: `docs/DEPLOY.md`. |
| Screen owner side | Owners could add screens and connect players, but could not see bookings | Owner dashboard (`/owner`): screens, players online, ads today, earned from plays shown. Booked slots per screen (`/orgs/{org}/screens/{screen}/schedule`): pick a day and hour, see the loop slot by slot and every ad in play order, and move an ad earlier or later (`loop_order`, used by `LoopPacking`; never changes capacity). The Raspberry Pi gets the new order with its next signed playlist (within a minute). Owner changes need two-factor; the lab can skip it with `OWNER_REQUIRE_MFA=false` (never in production). api, queue and scheduler now share one image (`dooh-api`), so the playlist signer can't run stale code. |
