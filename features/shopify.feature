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
        ]
      }
      """
    Then the response status code should be 200
    And a delivery should have been created for Shopify order "1234567890"
    And the delivery dropoff should be after "2026-07-15 10:00:00"
    And the delivery dropoff should be before "2026-07-15 12:00:00"

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
