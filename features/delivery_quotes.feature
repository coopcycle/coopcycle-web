Feature: Delivery quotes

  Scenario: Create and confirm delivery quote
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_taxation.yml |
      | sylius_products.yml |
      | stores.yml          |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries/quotes" with body:
      """
      {
        "pickup": {
          "address": "24, Rue de la Paix Paris",
          "before": "tomorrow 13:00"
        },
        "dropoff": {
          "address": "48, Rue de Rivoli Paris",
          "before": "tomorrow 15:00"
        }
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/DeliveryQuote",
        "@id":"/api/delivery_quotes/1",
        "@type":"DeliveryQuote",
        "amount":499,
        "currency":"EUR",
        "expiresAt":"@string@.isDateTime()"
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "PUT" request to "/api/deliveries/quotes/1/confirm" with body:
      """
      {}
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/DeliveryQuote",
        "@id":"/api/delivery_quotes/1",
        "@type":"DeliveryQuote",
        "delivery":"/api/deliveries/1"
      }
      """

  Scenario: Can't confirm delivery quote
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_taxation.yml |
      | sylius_products.yml |
      | stores.yml          |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries/quotes" with body:
      """
      {
        "pickup": {
          "address": "24, Rue de la Paix Paris",
          "before": "tomorrow 13:00"
        },
        "dropoff": {
          "address": "48, Rue de Rivoli Paris",
          "before": "tomorrow 15:00"
        }
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/DeliveryQuote",
        "@id":"/api/delivery_quotes/1",
        "@type":"DeliveryQuote",
        "amount":499,
        "currency":"EUR",
        "expiresAt":"@string@.isDateTime()"
      }
      """
    When the store with name "Acme2" has an OAuth client named "Acme2"
    And the OAuth client with name "Acme2" has an access token
    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme2" sends a "PUT" request to "/api/deliveries/quotes/1/confirm" with body:
      """
      {}
      """
    Then the response status code should be 403

  Scenario: Create delivery quote with multiple tasks and timeSlot
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_taxation.yml |
      | sylius_products.yml |
      | stores.yml          |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries/quotes" with body:
      """
      {
        "tasks": [
          {
            "type": "pickup",
            "address": "24, Rue de la Paix Paris"
          },
          {
            "type": "pickup",
            "address": "28, Rue de la Paix Paris"
          },
          {
            "type": "dropoff",
            "address": "48, Rue de Rivoli Paris",
            "timeSlot": "2022-08-12T10:00:00Z/2022-08-12T12:00:00Z"
          }
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/DeliveryQuote",
        "@id":"/api/delivery_quotes/1",
        "@type":"DeliveryQuote",
        "amount":499,
        "currency":"EUR",
        "expiresAt":"@string@.isDateTime()"
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "PUT" request to "/api/deliveries/quotes/1/confirm" with body:
      """
      {}
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/DeliveryQuote",
        "@id":"/api/delivery_quotes/1",
        "@type":"DeliveryQuote",
        "delivery":"/api/deliveries/1"
      }
      """
