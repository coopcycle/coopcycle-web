Feature: Task recurrence rules

  Scenario: Create recurrence rule (single task)
    Given the fixtures files are loaded:
      | users.yml           |
      | stores.yml          |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/recurrence_rules" with body:
      """
      {
        "store":"/api/stores/1",
        "rule":"FREQ=WEEKLY;",
        "name":"test rule",
        "template": {
          "@type":"Task",
          "address": {
            "streetAddress": "1, Rue de Rivoli, 75004 Paris"
          },
          "after":"11:30",
          "before":"12:00"
        }
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RecurrenceRule",
        "@id":"/api/recurrence_rules/1",
        "@type":"RecurrenceRule",
        "store":"/api/stores/1",
        "orgName":"Acme",
        "name":"test rule",
        "rule":"FREQ=WEEKLY",
        "template":{
          "@type":"Task",
          "address": {
            "streetAddress": @string@
          },
          "after":"11:30",
          "before":"12:00"
        },
        "arbitraryPriceTemplate": null,
        "isCancelled":false
      }
      """

  Scenario: Create recurrence rule (multiple tasks)
    Given the fixtures files are loaded:
      | users.yml           |
      | stores.yml          |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/recurrence_rules" with body:
      """
      {
        "store":"/api/stores/1",
        "rule":"FREQ=WEEKLY;",
        "template": {
          "@type":"hydra:Collection",
          "hydra:member": [
            {
              "@type":"Task",
              "address": {
                "streetAddress": "1, Rue de Rivoli, 75004 Paris",
                "telephone": "+33612345678"
              },
              "after":"11:30",
              "before":"12:00"
            },
            {
              "@type":"Task",
              "address": {
                "streetAddress": "10, Rue de Rivoli, 75004 Paris"
              },
              "after":"12:00",
              "before":"12:30"
            }
          ]
        }
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RecurrenceRule",
        "@id":"/api/recurrence_rules/1",
        "@type":"RecurrenceRule",
        "rule":"FREQ=WEEKLY",
        "store":"/api/stores/1",
        "orgName":"Acme",
        "name":null,
        "template": {
          "@type":"hydra:Collection",
          "hydra:member": [
            {
              "@type":"Task",
              "address": {
                "streetAddress": @string@,
                "telephone": "+33612345678"
              },
              "after":"11:30",
              "before":"12:00"
            },
            {
              "@type":"Task",
              "address": {
                "streetAddress": @string@
              },
              "after":"12:00",
              "before":"12:30"
            }
          ]
        },
        "arbitraryPriceTemplate": null,
        "isCancelled":false
      }
      """

  Scenario: Create recurrence rule (arbitrary price)
    Given the fixtures files are loaded:
      | users.yml           |
      | stores.yml          |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/recurrence_rules" with body:
      """
      {
        "store":"/api/stores/1",
        "rule":"FREQ=WEEKLY;",
        "template": {
          "@type":"hydra:Collection",
          "hydra:member": [
            {
              "@type":"Task",
              "address": {
                "streetAddress": "1, Rue de Rivoli, 75004 Paris",
                "telephone": "+33612345678"
              },
              "after":"11:30",
              "before":"12:00"
            },
            {
              "@type":"Task",
              "address": {
                "streetAddress": "10, Rue de Rivoli, 75004 Paris"
              },
              "after":"12:00",
              "before":"12:30"
            }
          ]
        },
        "arbitraryPriceTemplate": {
          "variantName":"Test product",
          "variantPrice":7200
        }
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RecurrenceRule",
        "@id":"/api/recurrence_rules/1",
        "@type":"RecurrenceRule",
        "rule":"FREQ=WEEKLY",
        "store":"/api/stores/1",
        "orgName":"Acme",
        "name":null,
        "template": {
          "@type":"hydra:Collection",
          "hydra:member": [
            {
              "@type":"Task",
              "address": {
                "streetAddress": @string@,
                "telephone": "+33612345678"
              },
              "after":"11:30",
              "before":"12:00"
            },
            {
              "@type":"Task",
              "address": {
                "streetAddress": @string@
              },
              "after":"12:00",
              "before":"12:30"
            }
          ]
        },
        "arbitraryPriceTemplate": {
          "variantName":"Test product",
          "variantPrice":7200
        },
        "isCancelled":false
      }
      """

  Scenario: Update recurrence rule (single task, new address)
    Given the fixtures files are loaded:
      | users.yml            |
      | addresses.yml        |
      | recurrence_rules.yml |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/recurrence_rules/1" with body:
      """
      {
        "template": {
          "@type":"Task",
          "address": {
            "streetAddress": "52, Rue de Rivoli, 75004 Paris"
          },
          "after":"11:30",
          "before":"12:30"
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RecurrenceRule",
        "@id":"/api/recurrence_rules/1",
        "@type":"RecurrenceRule",
        "store":"/api/stores/1",
        "orgName":"Acme",
        "name":null,
        "rule":"FREQ=WEEKLY",
        "template":{
          "@type":"Task",
          "address": {
            "streetAddress": @string@
          },
          "after":"11:30",
          "before":"12:30"
        },
        "arbitraryPriceTemplate": null,
        "isCancelled":false
      }
      """

  Scenario: Update recurrence rule address telephone (multiple tasks)
    Given the fixtures files are loaded:
      | users.yml            |
      | addresses.yml        |
      | recurrence_rules.yml |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/recurrence_rules/2" with body:
      """
      {
        "template": {
          "@type":"hydra:Collection",
          "hydra:member":[
            {
              "address":{
                "streetAddress":"272, rue Saint Honor\u00e9 75001 Paris 1er",
                "telephone":"+33612345678",
                "description":"Lorem ipsum",
                "contactName":"John Doe"
              },
              "after":"11:30",
              "before":"12:00"
            },
            {
              "address":{
                "streetAddress":"18, avenue Ledru-Rollin 75012 Paris 12\u00e8me"
              },
              "after":"12:30",
              "before":"13:00"
            }
          ]
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RecurrenceRule",
        "@id":"/api/recurrence_rules/2",
        "@type":"RecurrenceRule",
        "rule":"FREQ=WEEKLY;BYDAY=MO,FR",
        "template":{
          "@type":"hydra:Collection",
          "hydra:member":[
            {
              "address":{
                "streetAddress":"272, rue Saint Honor\u00e9 75001 Paris 1er",
                "telephone":"+33612345678",
                "description":"Lorem ipsum",
                "contactName":"John Doe"
              },
              "after":"11:30",
              "before":"12:00"
            },
            {
              "address":{
                "streetAddress":"18, avenue Ledru-Rollin 75012 Paris 12ème"
              },
              "after":"12:30",
              "before":"13:00"
            }
          ]
        },
        "store":"/api/stores/1",
        "orgName":"Acme",
        "name":null,
        "arbitraryPriceTemplate": null,
        "isCancelled":false
      }
      """

  Scenario: List recurrence rules
    Given the fixtures files are loaded:
      | users.yml            |
      | addresses.yml        |
      | recurrence_rules.yml |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/recurrence_rules"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RecurrenceRule",
        "@id":"/api/recurrence_rules",
        "@type":"hydra:Collection",
        "hydra:member": @array@,
        "hydra:totalItems":3
      }
      """

  Scenario: Get soft deleted recurrence rules
    Given the fixtures files are loaded:
      | users.yml            |
      | addresses.yml        |
      | recurrence_rules.yml |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/recurrence_rules/3"
    Then the response status code should be 404

  Scenario: Delete recurrence rules
    Given the fixtures files are loaded:
      | users.yml            |
      | addresses.yml        |
      | recurrence_rules.yml |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "DELETE" request to "/api/recurrence_rules/2"
    Then the response status code should be 204

  Scenario: Apply recurrence rule
    Given the fixtures files are loaded:
      | users.yml            |
      | addresses.yml        |
      | recurrence_rules.yml |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/recurrence_rules/2/between" with body:
      """
      {
        "after": "2021-02-12T00:00:00+01:00",
        "before": "2021-02-12T23:59:59+01:00"
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RecurrenceRule",
        "@id":"/api/recurrence_rules/2",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/tasks/1",
            "@type":"Task",
            "packages": [],
            "weight": null,
            "barcode": @array@
          },
          {
            "@id":"/api/tasks/2",
            "@type":"Task",
            "packages": [],
            "weight": null,
            "barcode": @array@
          }
        ],
        "hydra:totalItems":2
      }
      """

  Scenario: Apply recurrence rule creates delivery
    Given the fixtures files are loaded:
      | sylius_products.yml  |
      | sylius_taxation.yml  |
      | payment_methods.yml  |
      | users.yml            |
      | addresses.yml        |
      | recurrence_rules.yml |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/recurrence_rules/4/between" with body:
      """
      {
        "after": "2021-02-12T00:00:00+01:00",
        "before": "2021-02-12T23:59:59+01:00"
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/RecurrenceRule",
        "@id":"/api/recurrence_rules/4",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/tasks/1",
            "@type":"Task",
            "packages": [],
            "weight": null,
            "barcode": @array@
            },
          {
            "@id":"/api/tasks/2",
            "@type":"Task",
            "packages": [],
            "weight": null,
            "barcode": @array@
            },
          {
            "@id":"/api/tasks/3",
            "@type":"Task",
            "packages": [],
            "weight": null,
            "barcode": @array@
            }
        ],
        "hydra:totalItems":3
      }
      """

  Scenario: Generate orders based on the recurrence rules with an implicit timeSlot
    Given the current time is "2025-04-14 9:00:00"
    Given the fixtures files are loaded:
      | sylius_products.yml  |
      | sylius_taxation.yml  |
      | payment_methods.yml  |
      | users.yml            |
      | recurrence_rules_w_time_slot_pricing.yml |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/recurrence_rules/generate_orders?date=2025-04-14"
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/RecurrenceRule",
        "@id": "/api/recurrence_rules/generate_orders",
        "@type": "hydra:Collection",
        "hydra:member": [
          {
            "@id": "/api/orders/1",
            "@type": "http://schema.org/Order",
            "invitation": null,
            "paymentGateway": "stripe"
          }
        ],
        "hydra:totalItems": 1,
        "hydra:view": {
          "@id": "/api/recurrence_rules/generate_orders?date=2025-04-14",
          "@type": "hydra:PartialCollectionView"
        }
      }
      """
    Then the database should contain an order with a total price 699

  Scenario: Generate orders based on the recurrence rules with a range not belonging to a timeSlot
    Given the current time is "2025-04-21 11:00:00"
    Given the fixtures files are loaded:
      | sylius_products.yml  |
      | sylius_taxation.yml  |
      | payment_methods.yml  |
      | users.yml            |
      | recurrence_rules_w_distance_pricing.yml |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/recurrence_rules/generate_orders?date=2025-04-21"
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/RecurrenceRule",
        "@id": "/api/recurrence_rules/generate_orders",
        "@type": "hydra:Collection",
        "hydra:member": [
          {
            "@id": "/api/orders/1",
            "@type": "http://schema.org/Order",
            "invitation": null,
            "paymentGateway": "stripe"
          }
        ],
        "hydra:totalItems": 1,
        "hydra:view": {
          "@id": "/api/recurrence_rules/generate_orders?date=2025-04-21",
          "@type": "hydra:PartialCollectionView"
        }
      }
      """
    Then the database should contain an order with a total price 199

  Scenario: Can not generate orders based on the recurrence rules in the past
    Given the fixtures files are loaded:
      | sylius_products.yml  |
      | sylius_taxation.yml  |
      | payment_methods.yml  |
      | users.yml            |
      | recurrence_rules_w_distance_pricing.yml |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/recurrence_rules/generate_orders?date=2025-04-21"
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
      """
      {
      "@context": "/api/contexts/Error",
      "@type": "hydra:Error",
      "hydra:title": "An error occurred",
      "hydra:description": "Date must be in the future",
      "trace":@array@
      }
      """

  Scenario: Generate orders by-weekly based on the recurrence rules
    Given the current time is "2025-04-21 11:00:00"
    Given the fixtures files are loaded:
      | sylius_products.yml  |
      | sylius_taxation.yml  |
      | payment_methods.yml  |
      | users.yml            |
      | recurrence_rules_byweekly.yml |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/recurrence_rules/generate_orders?date=2025-04-21"
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/RecurrenceRule",
        "@id": "/api/recurrence_rules/generate_orders",
        "@type": "hydra:Collection",
        "hydra:member": [
          {
            "@id": "/api/orders/1",
            "@type": "http://schema.org/Order",
            "invitation": null,
            "paymentGateway": "stripe"
          }
        ],
        "hydra:totalItems": 1,
        "hydra:view": {
          "@id": "/api/recurrence_rules/generate_orders?date=2025-04-21",
          "@type": "hydra:PartialCollectionView"
        }
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/recurrence_rules/generate_orders?date=2025-04-28"
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/RecurrenceRule",
        "@id": "/api/recurrence_rules/generate_orders",
        "@type": "hydra:Collection",
        "hydra:member": [
        ],
        "hydra:totalItems": 0,
        "hydra:view": {
          "@id": "/api/recurrence_rules/generate_orders?date=2025-04-28",
          "@type": "hydra:PartialCollectionView"
        }
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/recurrence_rules/generate_orders?date=2025-05-05"
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/RecurrenceRule",
        "@id": "/api/recurrence_rules/generate_orders",
        "@type": "hydra:Collection",
        "hydra:member": [
          {
            "@id": "/api/orders/2",
            "@type": "http://schema.org/Order",
            "invitation": null,
            "paymentGateway": "stripe"
          }
        ],
        "hydra:totalItems": 1,
        "hydra:view": {
          "@id": "/api/recurrence_rules/generate_orders?date=2025-05-05",
          "@type": "hydra:PartialCollectionView"
        }
      }
      """

  Scenario: Dont generate orders based on the disabled recurrence rule
    Given the current time is "2025-04-14 9:00:00"
    Given the fixtures files are loaded:
      | sylius_products.yml  |
      | sylius_taxation.yml  |
      | payment_methods.yml  |
      | users.yml            |
      | recurrence_rules_disabled.yml |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/recurrence_rules/generate_orders?date=2025-04-15"
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/RecurrenceRule",
        "@id": "/api/recurrence_rules/generate_orders",
        "@type": "hydra:Collection",
        "hydra:member": [
        ],
        "hydra:totalItems": 0,
        "hydra:view": {
          "@id": "/api/recurrence_rules/generate_orders?date=2025-04-15",
          "@type": "hydra:PartialCollectionView"
        }
      }
      """

