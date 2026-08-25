# CoopCycle Shopify Gateway

A minimal PHP microservice that acts as the OAuth entry point for the CoopCycle Shopify App Store integration.

## Why does this exist?

CoopCycle is a **multi-tenant** platform: each cooperative runs its own instance at its own URL
(e.g. `paris.coopcycle.org`, `brussels.coopcycle.org`). The Shopify App Store, however, requires
a **single fixed App URL** and a **single OAuth redirect URI** registered in the Shopify Partner
dashboard. Pointing the App Store directly at one cooperative's URL would exclude all others.

This gateway solves the problem by sitting in front of all cooperative instances.

## Install flow

Shopify requires that an app **immediately authenticates using OAuth before any
other steps occur**, with no UI shown beforehand — it is enforced at App Store
review. So OAuth comes first, and the cooperative picker comes after it.

```
Shopify App Store
        │
        ▼  GET /shopify/install?shop=merchant.myshopify.com&hmac=...
┌─────────────────────────────────┐
│  shopify-gateway                │
│                                 │
│  1. Verify Shopify install HMAC │
│  2. Redirect straight to OAuth  │  ← renders nothing
│     (state signed by us)        │
└──────────────┬──────────────────┘
               │  Shopify OAuth consent screen
               ▼
┌─────────────────────────────────┐
│  shopify-gateway                │
│  GET /shopify/callback          │
│                                 │
│  3. Verify Shopify HMAC + our   │
│     own signed state            │
│  4. Exchange code for token     │
│  5. Park the token as a         │
│     "pending install" (1h TTL)  │
│  6. NOW show cooperative picker │
└──────────────┬──────────────────┘
               │  POST /shopify/start
               │  {pending, tenant_url}
               │
               │  Signed state carries the pending
               │  id — never the access token:
               │  base64({shop, tenant, pending,
               │          nonce, return_to})
               │  sig = HMAC(state, GATEWAY_SECRET)
               ▼
┌─────────────────────────────────┐
│  paris.coopcycle.org            │
│  GET /connect/shopify/choose-   │
│      store?state=...&sig=...    │
│                                 │
│  7. If not logged in →          │
│     redirect to CoopCycle login │
│  8. Merchant picks a store      │
│  9. Sign response:              │
│     return_sig = HMAC(          │
│       state + ':' + store_id,   │
│       GATEWAY_SECRET)           │
└──────────────┬──────────────────┘
               │  GET /shopify/oauth
               │  ?state=...&store_id=42&return_sig=...
               ▼
┌─────────────────────────────────┐
│  shopify-gateway                │
│                                 │
│  10. Verify return_sig          │
│  11. Reclaim the parked token,  │
│      checking it belongs to     │
│      this shop                  │
│  12. POST /connect/shopify/     │
│         provision               │
│  13. Delete the pending install │
│      and record shop → tenant   │
└──────────────┬──────────────────┘
               ▼
┌─────────────────────────────────┐
│  paris.coopcycle.org            │
│                                 │
│  Creates/updates ShopifyShop,   │
│  links it to the chosen Store,  │
│  registers webhooks             │
└─────────────────────────────────┘
```

Because the merchant chooses their cooperative *after* OAuth, the gateway holds
the access token for the two redirects in between. It is stored server-side as a
pending install with a one-hour TTL, deleted the moment the cooperative accepts
it, and never placed in a cookie or in any URL.

Only the gateway's domain needs to be registered in the Shopify Partner dashboard. Each
cooperative's instance never communicates directly with Shopify during the install flow.

## Environment variables

| Variable             | Description |
|----------------------|-------------|
| `SHOPIFY_API_KEY`    | Client ID from the Shopify Partner dashboard |
| `SHOPIFY_API_SECRET` | Client secret from the Shopify Partner dashboard |
| `GATEWAY_SECRET`     | A strong random secret (≥ 32 chars). Every CoopCycle tenant must set `SHOPIFY_GATEWAY_SECRET` to this same value. Generate with `openssl rand -hex 32`. |
| `APP_URL`            | The public HTTPS URL of this gateway, **without** a trailing slash. Example: `https://gateway.shopify.coopcycle.org` |
| `TENANTS`            | Optional. Comma-separated `Name:https://url` pairs shown in the install dropdown (`+` encodes a space). When empty, the install page falls back to a free-text URL field. |
| `SERVER_NAME`        | What FrankenPHP binds. `:8083` serves plain HTTP behind nginx (production); a bare domain makes Caddy obtain a Let's Encrypt certificate itself. |
| `GATEWAY_PORT`       | Port used by the container healthcheck and the dev port mapping. Keep in sync with `SERVER_NAME`. Defaults to `8083`. |
| `APP_ENV`            | `prod` (default) hides exception messages from the browser; `dev` shows them. |
| `SHOPS_DB_PATH`      | SQLite file recording which cooperative each shop installed on, used to route compliance webhooks. Defaults to `/data/shops/shops.sqlite`; must be on a persistent volume. |

## Runtime

The gateway runs on [FrankenPHP](https://frankenphp.dev) (Caddy + PHP 8.3 in a single
process), matching the deployment used by `coopcycle-ops/routing`. Configuration lives in
`frankenphp/Caddyfile` and `frankenphp/conf.d/app.ini`; both are baked into the image.

### Locally

```bash
cp .env.example .env
# Fill in the values in .env
docker compose -f compose.yaml -f compose.dev.yaml up --build
```

The service listens on port **8083** by default (override with `GATEWAY_PORT`).

### In production

CI builds and pushes `ghcr.io/coopcycle/coopcycle-web/shopify-gateway` on every change to
`shopify-gateway/` (see `.github/workflows/build_shopify_gateway_image.yml`). Deployment is
a separate manual step on the host:

```bash
# Secrets consumed by compose's env_file — never commit this.
cat > .env.prod.local <<'EOF'
SHOPIFY_API_KEY=...
SHOPIFY_API_SECRET=...
GATEWAY_SECRET=...
APP_URL=https://gateway.shopify.coopcycle.org
TENANTS="Paris:https://paris.coopcycle.org"
EOF
chmod 600 .env.prod.local

docker compose -f compose.yaml -f compose.prod.yaml pull
docker compose -f compose.yaml -f compose.prod.yaml up -d --remove-orphans
```

`compose.prod.yaml` puts the container on the host network, so FrankenPHP binds
`127.0.0.1:8083` and the box's existing nginx reverse-proxies
`gateway.shopify.coopcycle.org` to it — TLS is terminated by nginx, not Caddy. The gateway
is stateless: no volumes, no database. All install-flow state lives in the signed OAuth
`state` parameter.

## Shopify Partner dashboard setup

1. Go to [partners.shopify.com](https://partners.shopify.com) → **Apps** → your app.
2. Set **App URL** to `{APP_URL}/shopify/install`.
3. Under **Allowed redirection URL(s)**, add `{APP_URL}/shopify/callback`.
4. Copy the **API key** and **API secret** into your `.env`.

Only these two URLs need to be registered — one per gateway deployment.

## Tenant (CoopCycle instance) setup

Each CoopCycle cooperative must configure:

```dotenv
# .env on the cooperative's server
SHOPIFY_API_KEY=          # same as the gateway (used to initiate OAuth from the install page)
SHOPIFY_API_SECRET=       # same as the gateway (used for webhook HMAC verification)
SHOPIFY_GATEWAY_SECRET=   # same as the gateway's GATEWAY_SECRET
```

The tenant exposes two endpoints (part of `coopcycle-web`):

- `GET|POST /connect/shopify/choose-store` — shown to the merchant after they pick the
  cooperative. Requires the merchant to be logged in to CoopCycle with `ROLE_STORE`. Shows
  only the stores they manage.
- `POST /connect/shopify/provision` — called server-to-server by the gateway after OAuth
  completes. Accepts `{shop_domain, access_token, store_id}`. Authenticated via
  `Authorization: Bearer {GATEWAY_SECRET}`.

## Development

```bash
# Start the gateway locally, without Docker
APP_ENV=dev php -S localhost:8083 -t public

# Or with Docker (FrankenPHP, same image as production)
docker compose -f compose.yaml -f compose.dev.yaml up --build
```

To test the full flow locally, use [ngrok](https://ngrok.com) to expose both services:

```bash
ngrok http 8083   # for the gateway  → set APP_URL to this
ngrok http 80     # for the local CoopCycle tenant (served by nginx)
```

Update `APP_URL` in `shopify-gateway/.env` and `SHOPIFY_GATEWAY_SECRET` in both services.

## Compliance webhooks

Shopify requires every App Store app to handle `customers/data_request`,
`customers/redact` and `shop/redact`. These are **app-level**: Shopify posts them
to a single URI for every shop that ever installed the app, so in a multi-tenant
setup they can only land on the gateway.

```
Shopify ──POST /shopify/compliance──▶ gateway ──POST /connect/shopify/compliance──▶ paris.coopcycle.org
             (HMAC: API secret)                    (Bearer GATEWAY_SECRET)
```

The gateway verifies Shopify's HMAC, looks the shop up in `SHOPS_DB_PATH` — a
mapping written when the shop is provisioned — and forwards the payload to that
one cooperative. It deliberately never falls back to broadcasting: the
`customers/*` payloads contain the customer's email and phone, and sending those
to every cooperative would leak personal data to unrelated parties.

An unmapped shop answers `200 {"status":"unrouted"}` and logs a line for an
operator: Shopify retries cannot fix a missing mapping, and returning an error
would only bury the app in redelivery attempts.

This is the one piece of gateway state, which is why production mounts a volume
at `/data/shops`. It has to outlive the install because `shop/redact` arrives 48
hours after an uninstall.

## Security notes

- **Shopify install HMAC**: verified at `GET /shopify/install` to confirm the request came from Shopify.
- **Gateway → CoopCycle state token**: `base64({shop, tenant, nonce, return_to})` signed with `HMAC(state, GATEWAY_SECRET)`. CoopCycle verifies this before showing the store picker.
- **CoopCycle → Gateway return signature**: `HMAC(state + ':' + store_id, GATEWAY_SECRET)`. The gateway verifies this at `GET /shopify/oauth` to confirm CoopCycle authorised the chosen store — preventing a forged `store_id` in the redirect URL.
- **Shopify OAuth callback HMAC**: verified before the code is exchanged, preventing request forgery. The `{tenant, store_id}` Shopify state param is covered by this HMAC, making it tamper-proof.
- **Provision endpoint**: authenticated with `Authorization: Bearer {GATEWAY_SECRET}`. Never accessible publicly (token required).
- **Shopify OAuth state**: signed by the gateway with `GATEWAY_SECRET` and checked on the callback, so a state we did not mint is refused even though Shopify's own HMAC would pass. It expires after an hour, and the shop it names must match the shop Shopify reports.
- **Parked access token**: because OAuth now precedes the cooperative picker, the gateway holds the token between the callback and provisioning. It is kept server-side in SQLite for at most an hour, is reclaimed only via an id carried inside the CoopCycle-signed state, is checked against the shop it was issued for, and is deleted as soon as the cooperative accepts it. It is never put in a cookie, a URL, or the page. Replaying a completed install finds nothing to reclaim and is refused.
