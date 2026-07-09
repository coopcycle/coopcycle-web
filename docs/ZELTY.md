# Zelty Integration

[Zelty](https://zelty.fr) is a POS (point-of-sale) system used by some restaurants. The integration does two things:

1. **Catalog sync** — Zelty pushes its product catalog to CoopCycle so the ordering UI always reflects what's available in the POS.
2. **Order push** — When a customer places an order on CoopCycle, it is forwarded to Zelty so kitchen staff see it on the POS.

## Configuration

In the admin restaurant settings, an operator enters the **Zelty API key**. On save, CoopCycle automatically registers all webhooks with Zelty via `POST /webhooks`. The webhook secret key returned by Zelty is stored on the restaurant and used to verify subsequent webhook payloads.

Relevant form: `src/Form/Restaurant/ZeltyType.php`

---

## Zelty API calls (CoopCycle → Zelty)

All calls use Bearer token auth (`Authorization: Bearer <api_key>`).

| Method | Endpoint | When |
|--------|----------|------|
| `POST` | `orders` | Order placed on CoopCycle |
| `POST` | `orders/{id}/transactions` | Immediately after order creation |
| `POST` | `orders/{id}/closure` | Order marked as fulfilled |
| `POST` | `webhooks` | API key saved/changed in admin |
| `GET`  | `catalog/taxes` | During catalog import |

### Order push payload

```json
{
  "remote_id": "751",
  "display_id": "QF12345678",
  "fulfillment_type": "deliver_by_partner",
  "mode": "delivery",
  "source": "web",
  "due_date": "2026-07-01T12:00:00+02:00",
  "customer": { "remote_id": "42", "fname": "Jean", "name": "Dupont", "mail": "...", "phone": "..." },
  "address": { "name": "Chez Jean", "street": "12 rue de la Paix", "zip_code": "75001", "city": "Paris" },
  "items": [...],
  "total": 1670,
  "comment": "optional note"
}
```

Each item is either a **dish** or a **menu** depending on its Zelty product type (detected from the `zelty_id` prefix):

```json
// Dish (zelty_id starts with "ZD")
{ "id": 1269330, "remote_id": "ZD1269330_variant", "type": "dish", "price": 600,
  "modifiers": [{ "option_id": 276829, "option_value_id": 1403530, "quantity": 1, "price": 200 }] }

// Menu (zelty_id starts with "ZM")
{ "id": 87499, "remote_id": "ZM87499_variant", "type": "menu", "price": 1670,
  "dishes": [{ "id_part": 141228, "id": 1976713 }, { "id_part": 141229, "id": 907281 }] }
```

Items with `quantity > 1` are repeated as separate entries in the array.

### Transaction payload

Sent immediately after order creation with `close_if_paid: false` (closure is a separate step at fulfillment):

```json
{
  "transactions": [{ "name": "CB", "price": 1670 }],
  "close_if_paid": false
}
```

---

## Inbound webhooks (Zelty → CoopCycle)

All endpoints are under `/api/zelty/webhook/`. The catalog webhook verifies the `x-zelty-hmac-sha256` HMAC-SHA256 signature against the stored secret key.

### Catalog

| Endpoint | Event | Action |
|----------|-------|--------|
| `POST /api/zelty/webhook/catalog/{restaurantId}` | `catalog.push` | Full catalog import |

Triggers a complete import: taxes → options → dishes → menus → taxons/categories. Wrapped in a DB transaction; rolls back on failure.

### Dishes

| Endpoint | Event | Action |
|----------|-------|--------|
| `POST /api/zelty/webhook/dish.update` | `dish.update` | Enable/disable product |
| `POST /api/zelty/webhook/dish.delete` | `dish.delete` | Disable product |
| `POST /api/zelty/webhook/dish.availability_update` | `dish.availability_update` | Toggle enabled by `outofstock` flag |

### Menus

| Endpoint | Event | Action |
|----------|-------|--------|
| `POST /api/zelty/webhook/menu.update` | `menu.update` | Enable/disable menu product |
| `POST /api/zelty/webhook/menu.delete` | `menu.delete` | Disable menu product |
| `POST /api/zelty/webhook/menu.availability_update` | `menu.availability_update` | Toggle enabled by `outofstock` flag |

### Options

| Endpoint | Event | Action |
|----------|-------|--------|
| `POST /api/zelty/webhook/option.update` | `option.update` | Update option value enabled state and price |
| `POST /api/zelty/webhook/option_value.availability_update` | `option_value.availability_update` | Toggle option value by `outofstock` flag |

### Order status

| Endpoint | Event | Action |
|----------|-------|--------|
| `POST /api/zelty/webhook/order.status.update` | `order.status.update` | Update CoopCycle order state |

Status mapping:

| Zelty status | CoopCycle action |
|---|---|
| `production` | `OrderManager::startPreparing()` |
| `ready` | `OrderManager::finishPreparing()` |

---

## Order flow

```
Customer places order
        │
        ▼
OrderCreated event
        │
        ▼
DispatchZeltyPushOrder (skips if restaurant has no Zelty API key)
        │
        ▼  [command.bus async]
PushOrderHandler
        ├── ZeltyClient::pushToZelty()      → POST /orders
        ├── ZeltyClient::addTransaction()   → POST /orders/{id}/transactions
        └── saves zeltyOrderId on order
        
        [later, when courier delivers]
        
OrderFulfilled event
        │
        ▼
CloseZeltyOrder
        └── ZeltyClient::closeOrder()       → POST /orders/{id}/closure
```

The push is dispatched asynchronously via Symfony Messenger (`command.bus`), handled by the `php_worker` container.

---

## Catalog import

`ZeltyImportService` orchestrates the full import in this order:

1. **Taxes** (`ZeltyTaxesMapper`) — imports Zelty tax rules as Sylius tax categories.
2. **Options** (`ZeltyOptionMapper`) — imports modifier options (`ZO{id}_{restaurantId}`) and their values.
3. **Dishes** (`ZeltyProductMapper`) — imports dish products (`ZD*`), links their options.
4. **Menus** (`ZeltyMenuMapper`) — imports menu products (`ZM*`), creates menu-part options (`ZMP*`).
5. **Taxons** (`ZeltyTaxonMapper`) — assigns Zelty tags as product categories under a root taxon.

### Product ID conventions

| Prefix | Meaning | Example |
|--------|---------|---------|
| `ZD{id}` | Dish product | `ZD1269330` |
| `ZM{id}` | Menu product | `ZM87499` |
| `ZMP{id}` | Menu part (option group) | `ZMP141228` |
| `ZO{id}_{restaurantId}` | Modifier option | `ZO276829_46` |
| `ZMP{partId}_{dishId}` | Menu part option value | `ZMP141228_ZD1976713` |

### Menu products and dish options

A Zelty menu contains **parts**, each part listing **dishes** the customer can choose from. On import:

- Each menu part becomes a `ProductOption` (`ZMP*`) linked to the menu product with `enabled = true` — these appear as choice groups in the ordering UI.
- Each dish within a part has its own modifier options. Those dish-level options are also linked to the menu product but with `enabled = false`, so they don't appear as standalone option groups.
- `dependsOn` is set on dish option values pointing to the menu-part option value that corresponds to their dish. The frontend uses this to show/hide modifier choices depending on which dish the customer selected in each part.
