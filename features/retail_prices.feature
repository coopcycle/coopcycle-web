Feature: Retail prices

  Scenario: Get delivery price with JWT for admin user
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | stores.yml          |
    And the setting "subject_to_vat" has value "1"
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/retail_prices/calculate" with body:
      """
      {
        "store":"/api/stores/1",
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
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RetailPrice",
        "@id":@string@,
        "@type":"RetailPrice",
        "amount":499,
        "currency":"EUR",
        "tax":{
          "amount":83,
          "included": true
        },
        "items": [
          @...@
        ],
        "calculation": {"@*@":"@*@"}
      }
      """

  Scenario: Get delivery price with JWT for dispatcher user
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | stores.yml          |
    And the setting "subject_to_vat" has value "1"
    And the user "dispatcher" is loaded:
      | email      | dispatcher@coopcycle.org |
      | password   | 123456            |
    And the user "dispatcher" has role "ROLE_DISPATCHER"
    And the user "dispatcher" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "dispatcher" sends a "POST" request to "/api/retail_prices/calculate" with body:
      """
      {
        "store":"/api/stores/1",
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
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RetailPrice",
        "@id":@string@,
        "@type":"RetailPrice",
        "amount":499,
        "currency":"EUR",
        "tax":{
          "amount":83,
          "included": true
        },
        "items": [
          @...@
        ],
        "calculation": {"@*@":"@*@"}
      }
      """

  Scenario: Get delivery price with JWT (without tax)
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | stores.yml          |
    And the setting "subject_to_vat" has value "1"
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/retail_prices/calculate?tax=excluded" with body:
      """
      {
        "store":"/api/stores/1",
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
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RetailPrice",
        "@id":@string@,
        "@type":"RetailPrice",
        "amount":416,
        "currency":"EUR",
        "tax":{
          "amount":83,
          "included": false
        },
        "items": [
          @...@
        ],
        "calculation": {"@*@":"@*@"}
      }
      """

  Scenario: Get delivery price with packages (JWT)
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | stores.yml          |
    And the setting "subject_to_vat" has value "1"
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/retail_prices/calculate" with body:
      """
      {
        "store":"/api/stores/3",
        "packages": [
          {"type": "XL", "quantity": 2}
        ],
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
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RetailPrice",
        "@id":@string@,
        "@type":"RetailPrice",
        "amount":1299,
        "currency":"EUR",
        "tax":{
          "amount":217,
          "included": true
        },
        "items": [
          @...@
        ],
        "calculation": {"@*@":"@*@"}
      }
      """

  Scenario: Get delivery price with packages in task (JWT)
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | stores.yml          |
    And the setting "subject_to_vat" has value "1"
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/retail_prices/calculate" with body:
      """
      {
        "store":"/api/stores/3",
        "pickup": {
          "address": "24, Rue de la Paix Paris",
          "before": "tomorrow 13:00"
        },
        "dropoff": {
          "address": "48, Rue de Rivoli Paris",
          "before": "tomorrow 15:00",
          "packages": [
            {"type": "XL", "quantity": 2}
          ]
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RetailPrice",
        "@id":@string@,
        "@type":"RetailPrice",
        "amount":1299,
        "currency":"EUR",
        "tax":{
          "amount":217,
          "included": true
        },
        "items": [
          @...@
        ],
        "calculation": {"@*@":"@*@"}
      }
      """

  Scenario: Get delivery price with packages in task (quantity as string) (JWT)
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | stores.yml          |
    And the setting "subject_to_vat" has value "1"
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/retail_prices/calculate" with body:
      """
      {
        "store":"/api/stores/3",
        "pickup": {
          "address": "24, Rue de la Paix Paris",
          "before": "tomorrow 13:00"
        },
        "dropoff": {
          "address": "48, Rue de Rivoli Paris",
          "before": "tomorrow 15:00",
          "packages": [
            {"type": "XL", "quantity": "2"}
          ]
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RetailPrice",
        "@id":@string@,
        "@type":"RetailPrice",
        "amount":1299,
        "currency":"EUR",
        "tax":{
          "amount":217,
          "included": true
        },
        "items": [
          @...@
        ],
        "calculation": {"@*@":"@*@"}
      }
      """

  Scenario: Get delivery price with weight in task (JWT)
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | stores.yml          |
    And the setting "subject_to_vat" has value "1"
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/retail_prices/calculate" with body:
      """
      {
        "store":"/api/stores/4",
        "pickup": {
          "address": "24, Rue de la Paix Paris",
          "before": "tomorrow 13:00"
        },
        "dropoff": {
          "address": "48, Rue de Rivoli Paris",
          "before": "tomorrow 15:00",
          "weight": 1500
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RetailPrice",
        "@id":@string@,
        "@type":"RetailPrice",
        "amount":499,
        "currency":"EUR",
        "tax":{
          "amount":83,
          "included": true
        },
        "items": [
          @...@
        ],
        "calculation": {"@*@":"@*@"}
      }
      """

  Scenario: Get delivery price with latlLng (JWT)
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | stores.yml          |
    And the setting "subject_to_vat" has value "1"
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/retail_prices/calculate" with body:
      """
      {
        "store":"/api/stores/1",
        "weight": 12000,
        "packages": [
          {"type": "SMALL", "quantity": 2}
        ],
        "pickup": {
          "address": {
            "streetAddress": "24, Rue de la Paix Paris",
            "latLng": [48.870134, 2.332221]
          },
          "before": "tomorrow 13:00"
        },
        "dropoff": {
          "address": {
            "streetAddress": "48, Rue de Rivoli Paris",
            "latLng": [48.857127, 2.354766]
          },
          "before": "tomorrow 15:00"
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RetailPrice",
        "@id":@string@,
        "@type":"RetailPrice",
        "amount":499,
        "currency":"EUR",
        "tax":{
          "amount":83,
          "included": true
        },
        "items": [
          @...@
        ],
        "calculation": {"@*@":"@*@"}
      }
      """

  Scenario: Get delivery price with geo (JWT)
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | stores.yml          |
    And the setting "subject_to_vat" has value "1"
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/retail_prices/calculate" with body:
      """
      {
        "store":"/api/stores/1",
        "weight": 12000,
        "packages": [
          {"type": "SMALL", "quantity": 2}
        ],
        "pickup": {
          "address": {
            "streetAddress": "24, Rue de la Paix Paris",
            "geo": {"latitude": 48.870134, "longitude": 2.332221}
          },
          "before": "tomorrow 13:00"
        },
        "dropoff": {
          "address": {
            "streetAddress": "48, Rue de Rivoli Paris",
            "geo": {"latitude": 48.857127, "longitude": 2.354766}
          },
          "before": "tomorrow 15:00"
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RetailPrice",
        "@id":@string@,
        "@type":"RetailPrice",
        "amount":499,
        "currency":"EUR",
        "tax":{
          "amount":83,
          "included": true
        },
        "items": [
          @...@
        ],
        "calculation": {"@*@":"@*@"}
      }
      """

  Scenario: Get delivery price for an admin user with timeSlotUrl and timeSlot range in ISO 8601
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | stores.yml          |
    And the setting "subject_to_vat" has value "1"
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    Given the current time is "2020-04-02 11:00:00"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/retail_prices/calculate" with body:
      """
      {
        "store":"/api/stores/1",
        "pickup": {
          "address": "24, Rue de la Paix Paris",
          "timeSlotUrl": "/api/time_slots/1",
          "timeSlot": "2020-04-02T10:00:00Z/2020-04-02T12:00:00Z"
        },
        "dropoff": {
          "address": "48, Rue de Rivoli Paris",
          "timeSlotUrl": "/api/time_slots/1",
          "timeSlot": "2020-04-02T10:00:00Z/2020-04-02T12:00:00Z"
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RetailPrice",
        "@id":@string@,
        "@type":"RetailPrice",
        "amount":499,
        "currency":"EUR",
        "tax":{
          "amount":83,
          "included": true
        },
        "items": [
          @...@
        ],
        "calculation": {"@*@":"@*@"}
      }
      """

  Scenario: Get delivery price for an admin user with an implicit timeSlot
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | store_w_time_slot_pricing.yml |
    And the setting "subject_to_vat" has value "1"
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    Given the current time is "2020-04-02 11:00:00"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/retail_prices/calculate" with body:
      """
      {
        "store":"/api/stores/1",
        "pickup": {
          "address": "24, Rue de la Paix Paris",
          "after": "2020-04-02 12:00",
          "before": "2020-04-02 14:00"
        },
        "dropoff": {
          "address": "48, Rue de Rivoli Paris",
          "after": "2020-04-02 12:00",
          "before": "2020-04-02 14:00"
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RetailPrice",
        "@id":@string@,
        "@type":"RetailPrice",
        "amount":699,
        "currency":"EUR",
        "tax":{
          "amount":117,
          "included": true
        },
        "items": [
          @...@
        ],
        "calculation": {"@*@":"@*@"}
      }
      """

  Scenario: Get delivery price for an admin user with a range not belonging to a timeSlot
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | store_w_time_slot_pricing.yml |
    And the setting "subject_to_vat" has value "1"
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    Given the current time is "2020-04-02 11:00:00"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/retail_prices/calculate" with body:
      """
      {
        "store":"/api/stores/1",
        "pickup": {
          "address": "24, Rue de la Paix Paris",
          "after": "2020-04-02 12:10",
          "before": "2020-04-02 14:10"
        },
        "dropoff": {
          "address": "48, Rue de Rivoli Paris",
          "after": "2020-04-02 12:10",
          "before": "2020-04-02 14:10"
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RetailPrice",
        "@id":@string@,
        "@type":"RetailPrice",
        "amount":200,
        "currency":"EUR",
        "tax":{
          "amount":33,
          "included": true
        },
        "items": [
          @...@
        ],
        "calculation": {"@*@":"@*@"}
      }
      """

  Scenario: Can't get delivery price with invalid timeSlotUrl for admin user
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | stores.yml          |
    And the setting "subject_to_vat" has value "1"
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    Given the current time is "2020-04-02 11:00:00"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/retail_prices/calculate" with body:
      """
      {
        "store":"/api/stores/1",
        "pickup": {
          "address": "24, Rue de la Paix Paris",
          "timeSlotUrl": "/api/time_slots/123456",
          "timeSlot": "2020-04-02T10:00:00Z/2020-04-02T12:00:00Z"
        },
        "dropoff": {
          "address": "48, Rue de Rivoli Paris",
          "timeSlotUrl": "/api/time_slots/123456",
          "timeSlot": "2020-04-02T10:00:00Z/2020-04-02T12:00:00Z"
        }
      }
      """
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Error",
        "@type":"hydra:Error",
        "hydra:title":"An error occurred",
        "hydra:description": "Item not found for \"/api/time_slots/123456\".",
        "trace":@array@
      }
      """

  Scenario: Can't get delivery price with invalid timeSlot range for admin user
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | stores.yml          |
    And the setting "subject_to_vat" has value "1"
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    Given the current time is "2020-04-02 11:00:00"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/retail_prices/calculate" with body:
      """
      {
        "store":"/api/stores/1",
        "pickup": {
          "address": "24, Rue de la Paix Paris",
          "timeSlotUrl": "/api/time_slots/1",
          "timeSlot": "2020-04-02T10:05:00Z/2020-04-02T12:05:00Z"
        },
        "dropoff": {
          "address": "48, Rue de Rivoli Paris",
          "timeSlotUrl": "/api/time_slots/1",
          "timeSlot": "2020-04-02T10:05:00Z/2020-04-02T12:05:00Z"
        }
      }
      """
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Error",
        "@type":"hydra:Error",
        "hydra:title":"An error occurred",
        "hydra:description":"task.timeSlot.invalid",
        "trace":@array@
      }
      """

  Scenario: Get delivery price with OAuth
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | stores.yml          |
    And the setting "subject_to_vat" has value "1"
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/retail_prices/calculate" with body:
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
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RetailPrice",
        "@id":@string@,
        "@type":"RetailPrice",
        "amount":499,
        "currency":"EUR",
        "tax":{
          "amount":83,
          "included": true
        },
        "items": [
          @...@
        ],
        "calculation": {"@*@":"@*@"}
      }
      """

  Scenario: Get delivery price with array of tasks
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | stores.yml          |
    And the setting "subject_to_vat" has value "1"
    And the setting "latlng" has value "48.856613,2.352222"
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/retail_prices/calculate" with body:
      """
      {
        "tasks": [
          {
            "address": "24, Rue de la Paix Paris",
            "before": "tomorrow 13:00"
          },
          {
            "address": "48, Rue de Rivoli Paris",
            "before": "tomorrow 15:00"
          }
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RetailPrice",
        "@id":@string@,
        "@type":"RetailPrice",
        "amount":499,
        "currency":"EUR",
        "tax":{
          "amount":83,
          "included": true
        },
        "items": [
          @...@
        ],
        "calculation": {"@*@":"@*@"}
      }
      """

  Scenario: Get delivery price with multiple dropoffs
    Given the current time is "2021-08-25 09:00:00"
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | stores.yml          |
    And the setting "subject_to_vat" has value "1"
    And the setting "latlng" has value "48.856613,2.352222"
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/retail_prices/calculate" with body:
      """
      {
        "tasks": [
          {
            "type":"PICKUP",
            "address": "24, Rue de la Paix Paris",
            "timeSlot": "2021-08-25 10:00-11:00"
          },
          {
            "type":"DROPOFF",
            "address": "44, Rue de Rivoli Paris",
            "timeSlot": "2021-08-25 11:30-12:00"
          },
          {
            "type":"DROPOFF",
            "address": "48, Rue de Rivoli Paris",
            "timeSlot": "2021-08-25 11:30-13:00"
          }
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RetailPrice",
        "@id":@string@,
        "@type":"RetailPrice",
        "amount":499,
        "currency":"EUR",
        "tax":{
          "amount":83,
          "included": true
        },
        "items": [
          @...@
        ],
        "calculation": {"@*@":"@*@"}
      }
      """

  Scenario: Get delivery price with OAuth (implicit pickup)
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | stores.yml          |
    And the setting "subject_to_vat" has value "1"
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/retail_prices/calculate" with body:
      """
      {
        "dropoff": {
          "address": "48, Rue de Rivoli Paris",
          "before": "tomorrow 15:00"
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RetailPrice",
        "@id":@string@,
        "@type":"RetailPrice",
        "amount":499,
        "currency":"EUR",
        "tax":{
          "amount":83,
          "included": true
        },
        "items": [
          @...@
        ],
        "calculation": {"@*@":"@*@"}
      }
      """

  Scenario: Can't calculate a price for another store
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | stores.yml          |
    And the setting "subject_to_vat" has value "1"
    And the user "bob" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_STORE"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/retail_prices/calculate" with body:
      """
      {
        "store":"/api/stores/1",
        "dropoff": {
          "address": "48, Rue de Rivoli Paris, France",
          "before": "tomorrow 15:00"
        }
      }
      """
    Then the response status code should be 403

  Scenario: Get delivery price with JWT with explicit store
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | stores.yml          |
    And the setting "subject_to_vat" has value "1"
    And the user "bob" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_STORE"
    And the user "bob" is authenticated
    And the store with name "Acme" belongs to user "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/retail_prices/calculate" with body:
      """
      {
        "store":"/api/stores/1",
        "dropoff": {
          "address": "48, Rue de Rivoli Paris, France",
          "before": "tomorrow 15:00"
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RetailPrice",
        "@id":@string@,
        "@type":"RetailPrice",
        "amount":499,
        "currency":"EUR",
        "tax":{
          "amount":83,
          "included": true
        },
        "items": [
          @...@
        ],
        "calculation": {"@*@":"@*@"}
      }
      """

  Scenario: Get delivery price when there is two packages with the same name in different pricing rule set
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | store_w_package_pricing.yml |
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/retail_prices/calculate" with body:
      """
      {
        "store":"/api/stores/1",
        "packages": [
          {"type": "XL", "quantity": 2}
        ],
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
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RetailPrice",
        "@id":@string@,
        "@type":"RetailPrice",
        "amount":699,
        "currency":"EUR",
        "tax":{
          "amount":@integer@,
          "included": true
        },
        "items": [
          @...@
        ],
        "calculation": {"@*@":"@*@"}
      }
      """

  Scenario: Can't calculate a price
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | stores.yml          |
    And the setting "subject_to_vat" has value "1"
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/retail_prices/calculate" with body:
      """
      {
        "store":"/api/stores/9",
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
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Error",
        "@type":"hydra:Error",
        "hydra:title":"An error occurred",
        "hydra:description":"Le prix de la course n'a pas pu être calculé.",
        "calculation": {"@*@":"@*@"}
      }
      """
