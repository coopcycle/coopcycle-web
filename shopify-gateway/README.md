# CoopCycle Shopify Gateway

A minimal PHP microservice that acts as the OAuth entry point for the CoopCycle Shopify App Store integration.

## Why does this exist?

CoopCycle is a **multi-tenant** platform: each cooperative runs its own instance at its own URL
(e.g. `paris.coopcycle.org`, `brussels.coopcycle.org`). The Shopify App Store, however, requires
a **single fixed App URL** and a **single OAuth redirect URI** registered in the Shopify Partner
dashboard. Pointing the App Store directly at one cooperative's URL would exclude all others.

This gateway solves the problem by sitting in front of all cooperative instances:

```
Merchant (App Store)
        │
        ▼  GET /shopify/install?shop=merchant.myshopify.com
┌───────────────────────────┐
│  shopify-gateway          │  ← one Docker container, one domain
│  (this service)           │
│                           │
│  1. Shows cooperative     │
│     picker form           │
│  2. Starts Shopify OAuth  │
│     (redirect_uri = self) │
│  3. Receives callback,    │
│     exchanges code        │
│  4. POST /connect/shopify │
│       /provision          │
└───────────┬───────────────┘
            │  Bearer {GATEWAY_SECRET}
            │  {shop_domain, access_token}
            ▼
┌───────────────────────────┐
│  paris.coopcycle.org      │  (or any other tenant)
│                           │
│  Creates ShopifyShop,     │
│  registers webhooks &     │
│  FulfillmentService       │
└───────────────────────────┘
```

Only the gateway's URL needs to be registered in the Shopify Partner dashboard. Each
cooperative's instance never communicates directly with Shopify during the install flow.

## Environment variables

| Variable           | Description |
|--------------------|-------------|
| `SHOPIFY_API_KEY`  | Client ID from the Shopify Partner dashboard |
| `SHOPIFY_API_SECRET` | Client secret from the Shopify Partner dashboard |
| `GATEWAY_SECRET`   | A strong random secret (≥ 32 chars). Every CoopCycle tenant must set `SHOPIFY_GATEWAY_SECRET` to this same value. Generate with `openssl rand -hex 32`. |
| `APP_URL`          | The public HTTPS URL of this gateway, **without** a trailing slash. Example: `https://shopify-gateway.coopcycle.org` |

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
SHOPIFY_API_KEY=          # same as the gateway
SHOPIFY_API_SECRET=       # same as the gateway (used for webhook HMAC verification)
SHOPIFY_GATEWAY_SECRET=   # same as the gateway's GATEWAY_SECRET
```

The tenant exposes a `POST /connect/shopify/provision` endpoint (part of `coopcycle-web`)
that the gateway calls after a successful OAuth flow.

## Development

```bash
# Start the gateway locally
APP_ENV=dev php -S localhost:8080 -t public

# Or with Docker
docker compose up --build
```

To test the full flow locally, you can use [ngrok](https://ngrok.com) to expose the gateway and
a local CoopCycle instance to Shopify:

```bash
ngrok http 8080   # for the gateway
ngrok http 8000   # for the local CoopCycle tenant
```

Update `APP_URL` and `SHOPIFY_GATEWAY_SECRET` accordingly in both services.

## Security notes

- The gateway never stores the Shopify access token. It is forwarded directly to the tenant over HTTPS and immediately discarded.
- Gateway → tenant calls are authenticated with `Authorization: Bearer {GATEWAY_SECRET}`.
- The OAuth callback HMAC (signed by Shopify with the API secret) is verified before the code is exchanged, preventing request forgery.
- The tenant URL is embedded in the OAuth `state` parameter, which is covered by Shopify's HMAC, making it tamper-proof.
