# CoopCycle Shopify App

Shopify CLI configuration and extensions for the CoopCycle Shopify integration.

## How it works

The integration uses **Shopify Local Delivery**: merchants configure delivery
zones (by postal code or radius) directly in their Shopify admin under
*Settings → Shipping and delivery → Local delivery*. Zone filtering is handled
natively by Shopify — no Shopify Function is needed.

When a customer checks out with local delivery selected, they pick a delivery
date and time slot via the **CoopCycle Date Picker** checkout extension.
Shopify then fires an `orders/create` webhook to the CoopCycle tenant, which
creates a dispatch delivery with the chosen time window.

## Contents

```
shopify.app.toml.example          App config template
shopify.app.toml                  Your local config (gitignored)
extensions/
  checkout-date-picker/           Checkout UI Extension — date + time slot picker
```

## Setup

```bash
cp shopify.app.toml.example shopify.app.toml
```

Edit `shopify.app.toml` and replace:
- `YOUR_SHOPIFY_APP_CLIENT_ID` — the Client ID from your Shopify Partner dashboard.
- `YOUR_GATEWAY_URL` — the public URL of the deployed `shopify-gateway` service.

## Shopify Partner dashboard

1. Go to [partners.shopify.com](https://partners.shopify.com) → **Apps** → your app.
2. Set **App URL** to `{GATEWAY_URL}/shopify/install`.
3. Under **Allowed redirection URL(s)**, add `{GATEWAY_URL}/shopify/callback`.

## Development

```bash
# Requires Shopify CLI: https://shopify.dev/docs/api/shopify-cli
shopify app dev
```

This starts a local dev server, compiles the extension, and connects to your
dev store. The extension is hot-reloaded on file changes.

## Deployment

```bash
shopify app deploy
```

The CLI compiles the Checkout UI Extension (via Vite) and uploads it to
Shopify. No manual build step is needed.

After deploying, the merchant must add the date picker block to their checkout
layout: **Online Store → Customize → Checkout** → add the
**CoopCycle Date Picker** block.

## Checkout UI Extension

The extension (`extensions/checkout-date-picker/`) renders inside Shopify's
checkout when the customer selects local delivery. It:

1. Reads the CoopCycle tenant URL from a shop metafield (`coopcycle.tenant_url`)
   set automatically during app installation.
2. Fetches available delivery slots from `GET /api/shopify/slots?domain={shop}` on
   the CoopCycle tenant, using the store's configured time slot.
3. Presents a two-step picker: delivery date → time slot.
4. Writes the selection to order `note_attributes` (`Delivery Date`, `Delivery Time`),
   which the CoopCycle webhook processor reads to set the delivery time window.

## Required OAuth scopes

| Scope | Purpose |
|---|---|
| `read_orders` | Read incoming orders from the webhook payload |
| `write_fulfillments` | Mark orders as fulfilled once delivered |
| `read_fulfillments` | Read fulfillment state |
