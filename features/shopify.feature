Feature: Shopify webhook

  Background:
    Given the fixtures files are loaded:
      | shopify_shop.yml |

  Scenario: Receive orders/create webhook and create a delivery
    When I send a Shopify webhook for shop "test-shop.myshopify.com" with topic "orders/create" and body:
      """
      {
        "id": 1234567890,
        "name": "#1001",
        "note": null,
        "note_attributes": [
          { "name": "Delivery Date", "value": "2026-07-15" },
          { "name": "Delivery Time", "value": "10:00 - 12:00" }
        ],
        "shipping_address": {
          "first_name": "John",
          "last_name": "Doe",
          "address1": "48, Rue de Rivoli",
          "address2": null,
          "city": "Paris",
          "zip": "75004",
          "country": "France",
          "country_code": "FR",
          "phone": "+33600000000",
          "latitude": 48.855,
          "longitude": 2.352
        },
        "shipping_lines": [
          { "title": "Local Delivery", "code": "Local Delivery" }
        ],
        "fulfillment_orders": [
          { "id": 111, "delivery_method": { "method_type": "local" } }
        ]
      }
      """
    Then the response status code should be 200
    And a delivery should have been created for Shopify order "1234567890"
    And the delivery dropoff should be after "2026-07-15 10:00:00"
    And the delivery dropoff should be before "2026-07-15 12:00:00"

  Scenario: Ignore an order that was not placed with local delivery
    When I send a Shopify webhook for shop "test-shop.myshopify.com" with topic "orders/create" and body:
      """
      {
        "id": 2234567890,
        "name": "#1002",
        "note": null,
        "note_attributes": [],
        "shipping_address": {
          "first_name": "Jane",
          "last_name": "Roe",
          "address1": "48, Rue de Rivoli",
          "address2": null,
          "city": "Paris",
          "zip": "75004",
          "country": "France",
          "country_code": "FR",
          "phone": "+33600000000",
          "latitude": 48.855,
          "longitude": 2.352
        },
        "shipping_lines": [
          { "title": "Standard shipping", "code": "Standard" }
        ],
        "fulfillment_orders": [
          { "id": 222, "delivery_method": { "method_type": "shipping" } }
        ]
      }
      """
    Then the response status code should be 200
    And no delivery should have been created for Shopify order "2234567890"

  Scenario: Ignore a pickup order
    When I send a Shopify webhook for shop "test-shop.myshopify.com" with topic "orders/create" and body:
      """
      {
        "id": 3234567890,
        "name": "#1003",
        "note": null,
        "note_attributes": [],
        "shipping_address": null,
        "fulfillment_orders": [
          { "id": 333, "delivery_method": { "method_type": "pick_up" } }
        ]
      }
      """
    Then the response status code should be 200
    And no delivery should have been created for Shopify order "3234567890"

  Scenario: Reject webhook with invalid HMAC
    When I send a Shopify webhook for shop "test-shop.myshopify.com" with topic "orders/create" and invalid HMAC and body:
      """
      { "id": 9999 }
      """
    Then the response status code should be 403

  Scenario: Receive orders/cancelled webhook and cancel the delivery
    Given a Shopify order "1234567890" exists for shop "test-shop.myshopify.com" with a delivery
    When I send a Shopify webhook for shop "test-shop.myshopify.com" with topic "orders/cancelled" and body:
      """
      { "id": 1234567890, "name": "#1001" }
      """
    Then the response status code should be 200
    And the delivery for Shopify order "1234567890" should be cancelled

  Scenario: Reject a compliance request that is not signed with the gateway secret
    When I send a Shopify compliance request with topic "shop/redact" and a bad secret and body:
      """
      { "shop_id": 954889, "shop_domain": "test-shop.myshopify.com" }
      """
    Then the response status code should be 401

  Scenario: customers/redact strips personal data from the matching delivery
    Given a Shopify order "1234567890" exists for shop "test-shop.myshopify.com" with a delivery
    When I send a Shopify compliance request with topic "customers/redact" and body:
      """
      {
        "shop_id": 954889,
        "shop_domain": "test-shop.myshopify.com",
        "customer": { "id": 191167, "email": "john@example.com", "phone": "555-625-1199" },
        "orders_to_redact": [1234567890]
      }
      """
    Then the response status code should be 200
    And the dropoff address for Shopify order "1234567890" should be redacted
    And the deliveries should not have been deleted

  Scenario: customers/redact leaves orders of another shop alone
    Given a Shopify order "1234567890" exists for shop "test-shop.myshopify.com" with a delivery
    When I send a Shopify compliance request with topic "customers/redact" and body:
      """
      {
        "shop_id": 954889,
        "shop_domain": "other-shop.myshopify.com",
        "customer": { "id": 191167 },
        "orders_to_redact": [1234567890]
      }
      """
    Then the response status code should be 200
    And the dropoff address for Shopify order "1234567890" should not be redacted

  Scenario: customers/data_request reports without deleting anything
    Given a Shopify order "1234567890" exists for shop "test-shop.myshopify.com" with a delivery
    When I send a Shopify compliance request with topic "customers/data_request" and body:
      """
      {
        "shop_id": 954889,
        "shop_domain": "test-shop.myshopify.com",
        "customer": { "id": 191167, "email": "john@example.com" },
        "orders_requested": [1234567890],
        "data_request": { "id": 9999 }
      }
      """
    Then the response status code should be 200
    And the dropoff address for Shopify order "1234567890" should not be redacted

  Scenario: shop/redact removes the shop and its access token, keeping deliveries
    Given a Shopify order "1234567890" exists for shop "test-shop.myshopify.com" with a delivery
    When I send a Shopify compliance request with topic "shop/redact" and body:
      """
      { "shop_id": 954889, "shop_domain": "test-shop.myshopify.com" }
      """
    Then the response status code should be 200
    And no Shopify shop should exist for domain "test-shop.myshopify.com"
    And the deliveries should not have been deleted
