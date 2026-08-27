# CoopCycle Shopify App

Shopify CLI configuration and extensions for the CoopCycle Shopify integration.

## How it works

The integration uses **Shopify Local Delivery**: merchants configure delivery
zones (by postal code or radius) directly in their Shopify admin under
*Settings → Shipping and delivery → Local delivery*. Zone filtering is handled
natively by Shopify — no Shopify Function is needed, and it works on every
Shopify plan.

A customer picks their delivery date and time slot **in the cart**, via the
**CoopCycle Date Picker** theme app extension. On checkout Shopify fires an
`orders/create` webhook to the CoopCycle tenant, which creates a dispatch
delivery with the chosen time window. Orders that did not use local delivery are
ignored, so a merchant can offer regular shipping alongside it.

## Contents

```
shopify.app.toml.example          App config template
shopify.app.toml                  Your local config (gitignored)
extensions/
  cart-date-picker/               Theme app extension — delivery date + slot picker
    blocks/date_picker.liquid     The app block (target: "section")
    assets/coopcycle-date-picker.js
    locales/en.default.json
```

## Setup

```bash
cp shopify.app.toml.example shopify.app.toml
```

Then replace `YOUR_SHOPIFY_APP_CLIENT_ID` with the Client ID from your Shopify
Partner dashboard. The gateway URLs in the template already point at the
production gateway (`connect.coopcycle.org`); change them only if you
are running your own gateway.

## Shopify Partner dashboard

1. Go to [partners.shopify.com](https://partners.shopify.com) → **Apps** → your app.
2. Set **App URL** to `{GATEWAY_URL}/shopify/install`.
3. Under **Allowed redirection URL(s)**, add `{GATEWAY_URL}/shopify/callback`.

The mandatory privacy webhooks are declared in `shopify.app.toml` and point at
`{GATEWAY_URL}/shopify/compliance` — see the `shopify-gateway` README for how
they are routed to the right cooperative.

## Development

```bash
# Requires Shopify CLI: https://shopify.dev/docs/api/shopify-cli
shopify app dev
```

This starts a dev session, serves the extension, and connects to your dev store.
The block is hot-reloaded on file changes.

## Deployment

```bash
shopify app deploy
```

This uploads the extension and creates an app version. **The version must be
released** for the block to appear in a merchant's theme editor — check
*Partner Dashboard → your app → Versions* if it does not show up.

## Adding the date picker to a theme

Merchants do not have to do this by hand: the post-install page — served by the
gateway, and by the tenant on the direct install path — offers a **deep link**
that opens the theme editor on the Cart template with the block already
inserted, so all that is left is to press *Save*. The manual steps below are the
fallback shown alongside it.

That link is built from the theme app extension's **UUID**, which is not the
`uid` in `shopify.extension.toml`. Read it from the `theme_app_extension` module
in `.shopify/deploy-bundle/manifest.json` after `shopify app deploy`. Both sides
default to the published app's UUID; override it only when running your own
Shopify app, via `THEME_EXTENSION_UUID` on the gateway and
`SHOPIFY_THEME_EXTENSION_UUID` on the tenant.

The picker is a **theme app extension app block** targeting a section, so it is
added in the theme editor on the **Cart** template — not in the checkout editor,
and not under *App embeds*:

1. **Online Store → Themes → Customize** on the active theme
2. Switch the template selector at the top from *Default* to **Cart**
3. In the cart section → **Add block** → **Apps** → **CoopCycle Date Picker**

Two block settings are available, both optional:

| Setting | |
|---|---|
| **CoopCycle tenant URL** | Only needed as a fallback. The tenant URL is normally read from the `coopcycle.tenant_url` shop metafield, written during install. |
| **Delivery slot field label** | Label shown above the dropdown. Defaults to *Delivery slot*. |

### If the block is not listed

- **It is not under *App embeds*.** That list only contains blocks declared with
  `"target": "body"`; this one is `"target": "section"`.
- **The app version may not be released** — see Deployment above.
- **The theme's cart template must be an Online Store 2.0 JSON template** whose
  section accepts `@app` blocks. On a vintage theme, or one still using
  `templates/cart.liquid`, the *Apps* group does not appear at all.
- **The store must be on the deployed app version.** Reinstall the app on the
  dev store if it is running an older one.

### If the block is added but nothing renders

This is expected in some states and produces no visible error. The block renders
hidden (`display:none`) and only reveals itself once slots load successfully;
the fetch has a `catch` that deliberately leaves it hidden. It stays invisible
when:

- the `coopcycle.tenant_url` metafield is unset **and** the block's tenant URL
  setting is empty,
- the linked CoopCycle store has **no time slot configured**, so
  `/api/shopify/slots` returns an empty list,
- the request to the tenant fails (wrong URL, CORS, tenant down).

Open the cart page with the browser network tab and look for the call to
`/api/shopify/slots?domain=…` — which of the three it is will be obvious.

## Theme app extension

`extensions/cart-date-picker/` renders on the cart page. It:

1. Reads the CoopCycle tenant URL from the `coopcycle.tenant_url` shop metafield,
   falling back to the block setting.
2. Fetches available slots from `GET /api/shopify/slots?domain={shop}` on the
   tenant, which uses the linked store's configured time slot.
3. Renders **one combined dropdown** — e.g. *Monday 30 June, 10:00 - 12:00* —
   preselecting whatever is already saved on the cart.
4. Writes the choice to the cart as the `Delivery Date` and `Delivery Time`
   attributes (`POST /cart/update.js`). Shopify carries cart attributes through
   to the order's `note_attributes`, which the CoopCycle webhook processor reads
   to set the delivery time window.

## Required OAuth scopes

| Scope | Purpose |
|---|---|
| `read_orders` | Read incoming orders from the webhook payload |
| `write_fulfillments` | Mark orders as fulfilled once delivered |
| `read_fulfillments` | Read fulfillment state |
| `read_merchant_managed_fulfillment_orders` | Read `delivery_method.method_type` off an order's fulfillment orders, to skip orders that did not use local delivery. `read_fulfillments` does not cover this. |

Keep this list in sync with `shopify.app.toml`, the gateway's
`OAuthHandler::SCOPES`, and `ShopifyController::install()` on the tenant.
