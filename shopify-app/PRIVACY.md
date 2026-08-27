# CoopCycle Shopify App — Privacy Policy

**Last updated:** 26th August 2026

This policy explains what personal data the CoopCycle app for Shopify collects,
why, how long it is kept, and who it is shared with. It covers the app listed on
the Shopify App Store under the name **CoopCycle**, the OAuth gateway at
`connect.coopcycle.org`, and the CoopCycle instance operated by the
cooperative a merchant connects to.

## Who is responsible for your data

CoopCycle is a **federation of independent local cooperatives**. This matters for
who is accountable:

- **CoopCycle**, at **55, rue d'Orsel, 75018 Paris**, publishes the app and operates the
  OAuth gateway used during installation.
- The **cooperative a merchant connects to** (for example, a local bike-delivery
  cooperative) operates the CoopCycle instance that receives orders and carries
  out deliveries. It determines how delivery data is used in its own operations.

Merchants choose their cooperative during installation. If you are a customer of
a merchant using this app, the cooperative delivering your order is the party
that holds your delivery details.

## What data we collect, and from where

### From Shopify's APIs, about the merchant's shop

Collected when the app is installed and while it is in use:

| Data | Why |
|---|---|
| Shop domain (`example.myshopify.com`) | Identifies which shop an order belongs to, and which cooperative serves it |
| Shopify API access token | Lets the app read orders and update fulfillments for that shop |
| Webhook signing secret | Verifies that incoming webhooks genuinely come from Shopify |
| Delivery time slots of the linked cooperative | Offers customers a delivery time to choose from |

The app requests these access scopes and no others:
`read_orders`, `read_fulfillments`, `write_fulfillments`,
`read_merchant_managed_fulfillment_orders`.

The app also stores its own configuration — the web address of the cooperative
serving the shop — in an app-data metafield on its own app installation. This
needs no access scope, holds no personal data, and is visible only to this app.

### From Shopify's APIs, about the merchant's customers

When a customer places an order using **local delivery**, Shopify sends the app
an `orders/create` webhook. From it the app keeps only what is needed to make the
delivery:

- Recipient name
- Delivery address, and its geographic coordinates
- Telephone number
- The order note and the chosen delivery date and time slot
- The Shopify order number and identifier

**Orders that did not use local delivery are ignored entirely** — the app checks
the order's delivery method and discards anything else.

The app does **not** collect or store customer email addresses, payment or card
details, order contents, or order values.

### Directly from customers

The delivery date picker shown in the cart saves the chosen delivery date and
time slot as Shopify cart attributes. It sets **no cookies**, uses no analytics
or tracking technology, and reads nothing else from the browser.

### Automated logging

Servers keep ordinary technical logs (request metadata, errors) for security and
troubleshooting. Where a failure concerns a specific shop, the log entry may
include the shop domain.

## How the data is used

Solely to provide the service:

1. Turning a Shopify order into a delivery task for the cooperative.
2. Showing customers the delivery slots a cooperative can actually serve.
3. Giving the courier the address, recipient name and phone number needed to
   complete and, if necessary, arrange the delivery.
4. Reflecting delivery progress back to the shop.

We do **not** sell personal data, share it for advertising, use it to build
profiles, or use it to train machine-learning models.

## Who the data is shared with

- **The cooperative serving the merchant**, and the couriers assigned to a
  delivery, who see the delivery address, recipient name and phone number.
- **Shopify**, when the app writes fulfillment updates back to the shop.
- **A geocoding provider** — OpenCage or Google
  which converts a delivery address into coordinates. This only happens when
  Shopify does not already supply coordinates with the order.

There are no other recipients. Personal data is not transferred to any other
third party.

## Where data is processed

The gateway and CoopCycle instances are hosted in **France (European Union)** at **OVH and Scaleway**. The organisation
operates in Europe and is subject to the GDPR.

Please refer to geocoding providers privacy policies:

- https://policies.google.com/privacy?hl=en-US
- https://opencagedata.com/gdpr

## How long data is kept

| Data | Retention |
|---|---|
| Shopify access token and webhook secret | Until the app is uninstalled, then deleted when Shopify sends `shop/redact` (48 hours after uninstall) |
| Access token held by the gateway mid-installation | At most **one hour**, and deleted as soon as installation completes |
| Shop-to-cooperative mapping | Until the app is uninstalled and `shop/redact` is processed |
| Delivery records (address, name, phone) | **1 year**, after which personal details are removed |
| Technical logs | **3 months** |

Once a delivery's personal details are removed, the cooperative keeps the
delivery record itself — without recipient name, address or phone number — as
part of its operational and accounting history, and to meet record-keeping
obligations.

## Your rights

If you are in the EEA or the UK you have the right to access, correct, erase,
restrict or object to the processing of your personal data, and to data
portability. Comparable rights exist under other laws, including for California
residents.

**Customers** should contact the merchant they ordered from. Shopify forwards
such requests to us automatically, and we act on them within 30 days:

- a **request for a copy** of the data we hold produces a report the merchant can
  pass on;
- a **request for erasure** removes the recipient name, telephone number, address
  and any delivery note from the deliveries concerned.

**Merchants** may contact us directly at **dev@coopcycle.org**. Uninstalling
the app triggers deletion of the shop's credentials and data as described above.

You may also complain to your local data protection authority.

## Security

Access tokens and signing secrets are held only where the service needs them and
are removed when a shop uninstalls. All traffic between Shopify, the gateway and
a cooperative is encrypted in transit over HTTPS. Every webhook is verified with
a cryptographic signature before it is acted on, and requests between the gateway
and a cooperative are authenticated with a shared secret.

## Changes to this policy

Material changes will be reflected here, with the date at the top updated.
Merchants using the app will be notified of significant changes.

## Contact

**CoopCycle**
**55, rue d'Orsel, 75018 Paris**
Privacy enquiries: **contact@coopcycle.org**
