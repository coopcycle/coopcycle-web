# CoopCycle Shopify App

Shopify CLI configuration for the CoopCycle Shopify integration.

This directory contains no code — only the app configuration used by the
[Shopify CLI](https://shopify.dev/docs/api/shopify-cli) to manage the app in
the Shopify Partner dashboard.

## How it works

The integration uses **Shopify Local Delivery**: merchants configure delivery
zones (by postal code or radius) directly in their Shopify admin under
*Settings → Shipping and delivery → Local delivery*. No Shopify Function or
app extension is needed — zone filtering is handled natively by Shopify.

When a customer checks out with local delivery selected, Shopify fires an
`orders/create` webhook to the CoopCycle tenant, which creates a delivery in
the dispatch system.

## Setup

```bash
cp shopify.app.toml.example shopify.app.toml
```

Edit `shopify.app.toml` and replace:
- `YOUR_SHOPIFY_APP_CLIENT_ID` — the Client ID from your Shopify Partner dashboard app.
- `YOUR_GATEWAY_URL` — the public URL of the deployed `shopify-gateway` service.

## Shopify Partner dashboard

1. Go to [partners.shopify.com](https://partners.shopify.com) → **Apps** → your app.
2. Set **App URL** to `{GATEWAY_URL}/shopify/install`.
3. Under **Allowed redirection URL(s)**, add `{GATEWAY_URL}/shopify/callback`.

## Required OAuth scopes

| Scope | Purpose |
|---|---|
| `read_orders` | Read incoming orders from the webhook payload |
| `write_fulfillments` | Mark orders as fulfilled once delivered |
| `read_fulfillments` | Read fulfillment state |

No delivery customization or shipping scopes are required — local delivery
zone configuration is done entirely in the Shopify admin UI by the merchant.
