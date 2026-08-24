# CoopCycle Shopify Gateway

A minimal PHP microservice that acts as the OAuth entry point for the CoopCycle Shopify App Store integration.

## Why does this exist?

CoopCycle is a **multi-tenant** platform: each cooperative runs its own instance at its own URL
(e.g. `paris.coopcycle.org`, `brussels.coopcycle.org`). The Shopify App Store, however, requires
a **single fixed App URL** and a **single OAuth redirect URI** registered in the Shopify Partner
dashboard. Pointing the App Store directly at one cooperative's URL would exclude all others.

This gateway solves the problem by sitting in front of all cooperative instances.

## Install flow

```
Shopify App Store
        │
        ▼  GET /shopify/install?shop=merchant.myshopify.com&hmac=...
┌─────────────────────────────────┐
│  shopify-gateway                │
│                                 │
│  1. Verify Shopify install HMAC │
│  2. Show cooperative picker     │
│     (tenant URL input form)     │
└──────────────┬──────────────────┘
               │  POST /shopify/start
               │  {shop, tenant_url}
               │
               │  Builds signed state token:
               │  base64({shop, tenant, nonce, return_to})
               │  sig = HMAC(state, GATEWAY_SECRET)
               ▼
┌─────────────────────────────────┐
│  paris.coopcycle.org            │
│  GET /connect/shopify/choose-   │
│      store?state=...&sig=...    │
│                                 │
│  3. If not logged in →          │
│     redirect to CoopCycle login │
│  4. Show dropdown of stores     │
│     the merchant manages        │
│  5. Merchant picks a store      │
│  6. Sign response:              │
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
│  7. Verify return_sig           │
│  8. Start Shopify OAuth:        │
│     state = base64(             │
│       {tenant, store_id})       │
└──────────────┬──────────────────┘
               │  Shopify OAuth consent screen
               ▼
┌─────────────────────────────────┐
│  shopify-gateway                │
│  GET /shopify/callback          │
│                                 │
│  9.  Verify Shopify HMAC        │
│  10. Exchange code for token    │
│  11. POST /connect/shopify/     │
│        provision                │
│      {shop_domain,              │
│       access_token, store_id}   │
│      Authorization: Bearer ...  │
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

Only the gateway's domain needs to be registered in the Shopify Partner dashboard. Each
cooperative's instance never communicates directly with Shopify during the install flow.

## Environment variables

| Variable             | Description |
|----------------------|-------------|
| `SHOPIFY_API_KEY`    | Client ID from the Shopify Partner dashboard |
| `SHOPIFY_API_SECRET` | Client secret from the Shopify Partner dashboard |
| `GATEWAY_SECRET`     | A strong random secret (≥ 32 chars). Every CoopCycle tenant must set `SHOPIFY_GATEWAY_SECRET` to this same value. Generate with `openssl rand -hex 32`. |
| `APP_URL`            | The public HTTPS URL of this gateway, **without** a trailing slash. Example: `https://shopify-gateway.coopcycle.org` |
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
APP_URL=https://shopify-gateway.coopcycle.org
TENANTS="Paris:https://paris.coopcycle.org"
EOF
chmod 600 .env.prod.local

docker compose -f compose.yaml -f compose.prod.yaml pull
docker compose -f compose.yaml -f compose.prod.yaml up -d --remove-orphans
```

`compose.prod.yaml` puts the container on the host network, so FrankenPHP binds
`127.0.0.1:8083` and the box's existing nginx reverse-proxies
`shopify-gateway.coopcycle.org` to it — TLS is terminated by nginx, not Caddy. The gateway
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
- The gateway never stores the Shopify access token — it is forwarded to the tenant over HTTPS and immediately discarded.
