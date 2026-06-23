Feature: Shopify webhook integration

  Background:
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    And the store with name "Acme" has a Shopify shop

  Scenario: Receive orders/create webhook and create a delivery
    When I add "Content-Type" header equal to "application/json"
    And the Shopify shop for store "Acme" sends a "orders/create" webhook with body:
      """
      {
        "id": 1000000001,
        "name": "#1001",
        "shipping_address": {
          "address1": "18 Avenue Ledru-Rollin",
          "address2": null,
          "city": "Paris",
          "zip": "75012",
          "country": "FR",
          "latitude": 48.846656,
          "longitude": 2.369052,
          "first_name": "Jean",
          "last_name": "Dupont",
          "phone": "+33612345678"
        },
        "note": null,
        "note_attributes": []
      }
      """
    Then the response status code should be 200
    And a Shopify delivery should have been created for order "1000000001"

  Scenario: Receive orders/create webhook with requested_delivery_date note attribute
    When I add "Content-Type" header equal to "application/json"
    And the Shopify shop for store "Acme" sends a "orders/create" webhook with body:
      """
      {
        "id": 1000000002,
        "name": "#1002",
        "shipping_address": {
          "address1": "272 Rue Saint-Honoré",
          "city": "Paris",
          "zip": "75001",
          "country": "FR",
          "latitude": 48.864577,
          "longitude": 2.333338,
          "first_name": "Marie",
          "last_name": "Martin",
          "phone": "+33698765432"
        },
        "note": null,
        "note_attributes": [
          { "name": "requested_delivery_date", "value": "2026-06-25" }
        ]
      }
      """
    Then the response status code should be 200
    And a Shopify delivery should have been created for order "1000000002"

  Scenario: Duplicate orders/create webhook is idempotent
    Given a Shopify order "1000000003" exists for store "Acme"
    When I add "Content-Type" header equal to "application/json"
    And the Shopify shop for store "Acme" sends a "orders/create" webhook with body:
      """
      {
        "id": 1000000003,
        "name": "#1003",
        "shipping_address": {
          "address1": "18 Avenue Ledru-Rollin",
          "city": "Paris",
          "zip": "75012",
          "country": "FR",
          "latitude": 48.846656,
          "longitude": 2.369052,
          "first_name": "Jean",
          "last_name": "Dupont",
          "phone": "+33612345678"
        },
        "note": null,
        "note_attributes": []
      }
      """
    Then the response status code should be 200
    And exactly 1 Shopify delivery exists for order "1000000003"

  Scenario: Receive orders/cancelled webhook and cancel delivery tasks
    Given a Shopify order "1000000004" exists for store "Acme"
    When I add "Content-Type" header equal to "application/json"
    And the Shopify shop for store "Acme" sends a "orders/cancelled" webhook with body:
      """
      {
        "id": 1000000004,
        "name": "#1004"
      }
      """
    Then the response status code should be 200
    And the tasks for Shopify order "1000000004" should be cancelled

  Scenario: Reject webhook with invalid HMAC signature
    When I add "Content-Type" header equal to "application/json"
    And I add "X-Shopify-Topic" header equal to "orders/create"
    And I add "X-Shopify-Hmac-SHA256" header equal to "aW52YWxpZA=="
    And I send a "POST" request to "/api/shopify/webhook/1" with body:
      """
      {
        "id": 9999999999,
        "name": "#9999"
      }
      """
    Then the response status code should be 403

  Scenario: Receive CarrierService rate request and return a rate
    When I add "Content-Type" header equal to "application/json"
    And the Shopify shop for store "Acme" sends a CarrierService rates request with body:
      """
      {
        "rate": {
          "origin": {
            "country": "FR",
            "postal_code": "75001",
            "city": "Paris",
            "address1": "272 Rue Saint-Honoré"
          },
          "destination": {
            "country": "FR",
            "postal_code": "75012",
            "city": "Paris",
            "address1": "18 Avenue Ledru-Rollin"
          },
          "items": [],
          "currency": "EUR",
          "locale": "fr"
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "rates": "@array@"
      }
      """
