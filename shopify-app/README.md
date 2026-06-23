# CoopCycle Shopify App

Shopify App for CoopCycle — enables last-mile delivery by cooperatives directly from a Shopify store.

## How it works

1. A Shopify merchant installs the CoopCycle app from the App Store.
2. The **shopify-gateway** microservice (see `../shopify-gateway/`) handles OAuth and provisions each cooperative tenant.
3. The **Delivery Customization Function** (`extensions/delivery-customization/`) filters out CoopCycle shipping options at checkout when the customer's postal code is outside the cooperative's delivery zone.

## Directory structure

```
shopify-app/
├── shopify.app.toml                    — App-level Shopify CLI config
└── extensions/
    └── delivery-customization/
        ├── shopify.extension.toml      — Extension config (type, target)
        ├── package.json
        ├── src/
        │   ├── index.js                — Function logic (ES module)
        │   └── index.graphql           — Input query (cart + shop metafield)
        └── dist/
            └── index.wasm              — Compiled output (git-ignored)
```

## Prerequisites

- [Shopify CLI v3](https://shopify.dev/docs/apps/tools/cli)
- Node.js 18+

## Development

```bash
cd extensions/delivery-customization
npm install
npm run build          # Compiles src/index.js → dist/index.wasm
```

## How the zone filter works

The cooperative configures its delivery postal codes in the CoopCycle admin
(`/admin/stores/{id}/shopify`). CoopCycle stores those codes in a Shopify shop
metafield:

| Property  | Value                    |
|-----------|--------------------------|
| namespace | `coopcycle`              |
| key       | `delivery_postal_codes`  |
| type      | `json`                   |
| value     | `["75010","75011",...]`  |

At checkout, the Shopify Function reads this metafield. If the buyer's postal
code is **not** in the list, any delivery option whose title contains the word
"coopcycle" is hidden. If the metafield is absent or empty, all options are
shown (fail-open).

## Deployment

Deploy with Shopify CLI from the `shopify-app/` directory:

```bash
shopify app deploy
```

The `shopify.app.toml` must be updated with your real `client_id` and gateway URL before deploying.
