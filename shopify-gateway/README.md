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
│  registers webhooks &           │
│  FulfillmentService             │
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

## Running with Docker

```bash
cp .env.example .env
# Fill in the values in .env
docker compose up --build
```

The service listens on port **8080** by default.

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
# Start the gateway locally
APP_ENV=dev php -S localhost:8080 -t public

# Or with Docker
docker compose up --build
```

To test the full flow locally, use [ngrok](https://ngrok.com) to expose both services:

```bash
ngrok http 8080   # for the gateway  → set APP_URL to this
ngrok http 8000   # for the CoopCycle tenant
```

Update `APP_URL` in `shopify-gateway/.env` and `SHOPIFY_GATEWAY_SECRET` in both services.

## Security notes

- **Shopify install HMAC**: verified at `GET /shopify/install` to confirm the request came from Shopify.
- **Gateway → CoopCycle state token**: `base64({shop, tenant, nonce, return_to})` signed with `HMAC(state, GATEWAY_SECRET)`. CoopCycle verifies this before showing the store picker.
- **CoopCycle → Gateway return signature**: `HMAC(state + ':' + store_id, GATEWAY_SECRET)`. The gateway verifies this at `GET /shopify/oauth` to confirm CoopCycle authorised the chosen store — preventing a forged `store_id` in the redirect URL.
- **Shopify OAuth callback HMAC**: verified before the code is exchanged, preventing request forgery. The `{tenant, store_id}` Shopify state param is covered by this HMAC, making it tamper-proof.
- **Provision endpoint**: authenticated with `Authorization: Bearer {GATEWAY_SECRET}`. Never accessible publicly (token required).
- The gateway never stores the Shopify access token — it is forwarded to the tenant over HTTPS and immediately discarded.
