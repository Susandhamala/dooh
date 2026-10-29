# Sign in with rentallnepal: the contract

rentallnepal.com is the identity provider. The ads app never sees a rentallnepal password or
JWT, and the two apps share no secret except the client secret below.

## Flow

```
Browser (rentallnepal.com)      rentallnepal API              ads portal / ads API
  click "Screen Ads"  ─────────────────────────────────────▶  /sso/start
                                                               state = random, kept in sessionStorage
  ◀─────────── redirect https://rentallnepal.com/screen-ads/authorize?state=…
  Vue app (logged in) ── POST /api/v1/sso/ads/code ─▶
                         ◀── { code } (Redis, 60 s, single use, bound to user UUID + redirect_uri)
  redirect https://ads.rentallnepal.com/sso/callback?code=…&state=…  ─▶  portal checks state
                                                               POST /api/v1/auth/sso { code }
                                rentallnepal  ◀── POST /api/v1/sso/ads/exchange (server to server)
                                              ──▶ { uuid, name, email, phone, *_verified }
                                                               ads JWT (own secret, 15 min)
```

## What rentallnepal must implement

### `GET /screen-ads/authorize?state=…` (Vue route)

If not logged in, log in first and come back. Then call the code endpoint and redirect to
`https://ads.rentallnepal.com/sso/callback?code=<code>&state=<state>` with `state` passed through
unchanged. Only ever redirect to that exact URL.

### `POST /api/v1/sso/ads/code` (behind `auth:api`)

- Generate `bin2hex(random_bytes(32))`.
- Store in Redis for 60 s: key `sso:ads:<sha256(code)>` → `{user_uuid, redirect_uri: "https://ads.rentallnepal.com/sso/callback"}`.
- Respond `{success: true, data: {code}}`.

### `POST /api/v1/sso/ads/exchange` (server to server)

- Auth: `Authorization: Bearer <ADS_SSO_CLIENT_SECRET>`, compared with `hash_equals`. Restrict by
  source IP to the VPS if possible.
- Body: `{code, redirect_uri}`.
- `GETDEL` the Redis key (single use, atomic). Reject if missing, or if `redirect_uri` differs.
- Reject if the user is disabled.
- Respond with only:

```json
{"success": true, "data": {
  "uuid": "…", "name": "…", "email": "…", "phone": "98…",
  "email_verified": true, "phone_verified": false
}}
```

Any failure: HTTP 401 with the standard error envelope. The ads app shows a generic
"sign-in failed" message either way.

## Ads side (this repo)

- Portal: `/sso/start` and `/sso/callback` (state check), `portal/src/views/SsoCallbackView.vue`.
- API: `POST /api/v1/auth/sso` → `App\Services\Sso\SsoService`, client in `RentallSsoClient`.
- Config: `RENTALL_BASE_URL`, `RENTALL_SSO_CLIENT_SECRET` in `api/.env`.
- An existing *local* ads account with the same email is never merged automatically (409).
- Lab-only local login (`LAB_LOCAL_LOGIN=true`) keeps thesis tests independent of the live site.
