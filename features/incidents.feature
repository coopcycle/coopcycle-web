Feature: Incidents

  Scenario: Report incident
    Given the fixtures files are loaded:
      | tasks.yml |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/incidents" with body:
      """
      {
        "description": "PACKAGE WET",
        "failureReasonCode": "DAMAGED",
        "task": "/api/tasks/2"
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Incident",
        "@id":"/api/incidents/1",
        "@type":"Incident",
        "id":@integer@,
        "title":"Endommagé",
        "status":"OPEN",
        "priority":@integer@,
        "task":"/api/tasks/2",
        "failureReasonCode":"DAMAGED",
        "description":"PACKAGE WET",
        "images":[],
        "events":[],
        "createdBy":"/api/users/1",
        "createdAt":"@string@.isDateTime()",
        "updatedAt":"@string@.isDateTime()",
        "tags":[],
        "metadata": {"@*@": "@*@"}
      }
      """
    Given I add "Content-Type" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/incidents/1"
    Then the response status code should be 200

  Scenario: Report incident (with metadata)
    Given the fixtures files are loaded:
      | tasks.yml |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/incidents" with body:
      """
      {
        "description": "PACKAGE WET",
        "failureReasonCode": "DAMAGED",
        "task": "/api/tasks/2",
        "metadata": [
          {"foo":"bar"},
          {"baz":"bat"}
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Incident",
        "@id":"@string@",
        "@type":"Incident",
        "id":@integer@,
        "title":"Endommagé",
        "status":"OPEN",
        "priority":@integer@,
        "task":"/api/tasks/2",
        "failureReasonCode":"DAMAGED",
        "description":"PACKAGE WET",
        "images":[],
        "events":[],
        "createdBy":"/api/users/1",
        "createdAt":"@string@.isDateTime()",
        "updatedAt":"@string@.isDateTime()",
        "tags":[],
        "metadata": [
          {"foo":"bar"},
          {"baz":"bat"}
        ]
      }
      """

  Scenario: Report incident: suggest order details modification
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | store_basic.yml |
      | package_delivery_order.yml |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/incidents" with body:
      """
      {
        "description": "Wrong order details",
        "failureReasonCode": "INCORRECT_ITEM",
        "task": "/api/tasks/2",
        "metadata": [
          {
            "suggestion": {
              "tasks": [
                {
                  "id": 2
                },
                {
                  "id": 1,
                  "packages": [
                    {"type": "XL", "quantity": 2}
                  ],
                  "weight": 30000
                }
              ]
            }
          }
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Incident",
        "@id":"@string@",
        "@type":"Incident",
        "id":@integer@,
        "title":"Article incorrect",
        "status":"OPEN",
        "priority":@integer@,
        "task":"/api/tasks/2",
        "failureReasonCode":"INCORRECT_ITEM",
        "description":"Wrong order details",
        "images":[],
        "events":[],
        "createdBy":"/api/users/2",
        "createdAt":"@string@.isDateTime()",
        "updatedAt":"@string@.isDateTime()",
        "tags":[],
        "metadata": [
          {
            "suggestion": {
              "id": 1,
              "tasks": [
                {
                  "id": 2
                },
                {
                  "id": 1,
                  "packages": [
                    {"type": "XL", "quantity": 2}
                  ],
                  "weight": 30000
                }
              ]
            }
          }
        ]
      }
      """

  Scenario: Report incident: suggest order details modification with tasks in reversed order (incorrect request, but should be handled and tasks reordered)
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | store_basic.yml |
      | package_delivery_order.yml |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/incidents" with body:
      """
      {
        "description": "Wrong order details",
        "failureReasonCode": "INCORRECT_ITEM",
        "task": "/api/tasks/2",
        "metadata": [
          {
            "suggestion": {
              "tasks": [
                {
                  "id": 1,
                  "packages": [
                    {"type": "XL", "quantity": 2}
                  ],
                  "weight": 30000
                },
                {
                  "id": 2
                }
              ]
            }
          }
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Incident",
        "@id":"@string@",
        "@type":"Incident",
        "id":@integer@,
        "title":"Article incorrect",
        "status":"OPEN",
        "priority":@integer@,
        "task":"/api/tasks/2",
        "failureReasonCode":"INCORRECT_ITEM",
        "description":"Wrong order details",
        "images":[],
        "events":[],
        "createdBy":"/api/users/2",
        "createdAt":"@string@.isDateTime()",
        "updatedAt":"@string@.isDateTime()",
        "tags":[],
        "metadata": [
          {
            "suggestion": {
              "id": 1,
              "tasks": [
                {
                  "id": 2
                },
                {
                  "id": 1,
                  "packages": [
                    {"type": "XL", "quantity": 2}
                  ],
                  "weight": 30000
                }
              ]
            }
          }
        ]
      }
      """

  Scenario: Report incident: suggest order supplements
    Given the fixtures files are loaded:
      | tasks.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | store_with_range_supplements.yml |
      | package_delivery_order.yml |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/incidents" with body:
      """
      {
        "description": "Waiting time",
        "failureReasonCode": "INCORRECT_ITEM",
        "task": "/api/tasks/2",
        "metadata": [
          {
            "suggestion": {
              "order": {
                "manualSupplements": [
                  {
                    "pricingRule": "/api/pricing_rules/2",
                    "quantity": 10
                  }
                ]
              }
            }
          }
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Incident",
        "@id":"@string@",
        "@type":"Incident",
        "id":@integer@,
        "title":"Article incorrect",
        "status":"OPEN",
        "priority":@integer@,
        "task":"/api/tasks/2",
        "failureReasonCode":"INCORRECT_ITEM",
        "description":"Waiting time",
        "images":[],
        "events":[],
        "createdBy":"/api/users/2",
        "createdAt":"@string@.isDateTime()",
        "updatedAt":"@string@.isDateTime()",
        "tags":[],
        "metadata": [
          {
            "suggestion": {
              "id": 1,
              "tasks": [
                {
                  "id": 2
                },
                {
                  "id": 1
                }
              ],
              "order": {
                "manualSupplements": [
                  {
                    "pricingRule": "/api/pricing_rules/2",
                    "quantity": 10
                  }
                ]
              }
            }
          }
        ]
      }
      """
    
  Scenario: Report incident: with invalid suggestion in metadata
    Given the fixtures files are loaded:
      | tasks.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | sylius_products.yml |
      | store_with_range_supplements.yml |
      | package_delivery_order.yml |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/incidents" with body:
      """
      {
        "description": "Waiting time",
        "failureReasonCode": "INCORRECT_ITEM",
        "task": "/api/tasks/2",
        "metadata": [
          {
            "suggestion": {
            }
          }
        ]
      }
      """
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/ConstraintViolationList",
        "@type": "ConstraintViolationList",
        "hydra:title": "An error occurred",
        "hydra:description": "metadata[0][suggestion]: The suggestion field in metadata is not valid",
        "violations": [
          {
            "propertyPath": "metadata[0][suggestion]",
            "message": "The suggestion field in metadata is not valid",
            "code": null
          }
        ]
      }
      """

  Scenario: Report incident: pre-fill missing tasks in suggestion
    Given the fixtures files are loaded:
      | sylius_taxation.yml        |
      | payment_methods.yml        |
      | sylius_products.yml        |
      | store_basic.yml            |
      | package_delivery_order.yml |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/incidents" with body:
      """
      {
        "description": "Wrong dropoff details",
        "failureReasonCode": "INCORRECT_ITEM",
        "task": "/api/tasks/2",
        "metadata": [
          {
            "suggestion": {
              "tasks": [
                {
                  "id": 2,
                  "packages": [
                    {"type": "XL", "quantity": 5}
                  ],
                  "weight": 50000
                }
              ]
            }
          }
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Incident",
        "@id":"@string@",
        "@type":"Incident",
        "id":@integer@,
        "title":"Article incorrect",
        "status":"OPEN",
        "priority":@integer@,
        "task":"/api/tasks/2",
        "failureReasonCode":"INCORRECT_ITEM",
        "description":"Wrong dropoff details",
        "images":[],
        "events":[],
        "createdBy":"/api/users/2",
        "createdAt":"@string@.isDateTime()",
        "updatedAt":"@string@.isDateTime()",
        "tags":[],
        "metadata": [
          {
            "suggestion": {
              "id": 1,
              "tasks": [
                {
                  "id": 2,
                  "packages": [
                    {"type": "XL", "quantity": 5}
                  ],
                  "weight": 50000
                },
                {
                  "id": 1
                }
              ]
            }
          }
        ]
      }
      """

  Scenario: Accept suggestion
    Given the fixtures files are loaded:
      | sylius_taxation.yml             |
      | payment_methods.yml             |
      | sylius_products.yml             |
      | store_with_manual_supplements.yml |
      | package_delivery_order.yml      |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "dispatcher" is loaded:
      | email      | dispatcher@coopcycle.org |
      | password   | 123456            |
    And the user "dispatcher" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/incidents" with body:
      """
      {
        "description": "Wrong order details",
        "failureReasonCode": "INCORRECT_ITEM",
        "task": "/api/tasks/2",
        "metadata": [
          {
            "suggestion": {
              "tasks": [
                {
                  "id": 2
                },
                {
                  "id": 1,
                  "packages": [
                    {"type": "XL", "quantity": 2}
                  ],
                  "weight": 30000
                }
              ]
            }
          }
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Incident",
        "@id":"@string@",
        "@type":"Incident",
        "id":@integer@,
        "title":"Article incorrect",
        "status":"OPEN",
        "priority":@integer@,
        "task":"/api/tasks/2",
        "failureReasonCode":"INCORRECT_ITEM",
        "description":"Wrong order details",
        "images":[],
        "events":[],
        "createdBy":"/api/users/2",
        "createdAt":"@string@.isDateTime()",
        "updatedAt":"@string@.isDateTime()",
        "tags":[],
        "metadata": [
          {
            "suggestion": {
              "id": 1,
              "tasks": [
                {
                  "id": 2
                },
                {
                  "id": 1,
                  "packages": [
                    {"type": "XL", "quantity": 2}
                  ],
                  "weight": 30000
                }
              ]
            }
          }
        ]
      }
      """
    And the database should contain an order with a total price 499
    Given the user "dispatcher" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "dispatcher" sends a "PUT" request to "/api/incidents/1/action" with body:
      """
      {
        "action": "accepted_suggestion"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Incident",
        "@id":"@string@",
        "@type":"Incident",
        "id":@integer@,
        "title":"Article incorrect",
        "status":"OPEN",
        "priority":@integer@,
        "task":"/api/tasks/2",
        "failureReasonCode":"INCORRECT_ITEM",
        "description":"Wrong order details",
        "images":[],
        "events":[
          {
            "@type": "IncidentEvent",
            "@id":"@string@",
            "id":@integer@,
            "type":"accepted_suggestion",
            "message":null,
            "metadata":{
              "diff": null
            },
            "createdBy":"/api/users/3",
            "createdAt":"@string@.isDateTime()"
          }
        ],
        "createdBy":"/api/users/2",
        "createdAt":"@string@.isDateTime()",
        "updatedAt":"@string@.isDateTime()",
        "tags":[],
        "metadata": [
          {
            "suggestion": {
              "id": 1,
              "tasks": [
                {
                  "id": 2
                },
                {
                  "id": 1,
                  "packages": [
                    {"type": "XL", "quantity": 2}
                  ],
                  "weight": 30000
                }
              ]
            }
          }
        ]
      }
      """
    # Base: 499, weight: 250
    And the database should contain an order with a total price 749

  Scenario: Reject suggestion
    Given the fixtures files are loaded:
      | sylius_taxation.yml        |
      | payment_methods.yml        |
      | sylius_products.yml        |
      | store_with_manual_supplements.yml |
      | package_delivery_order.yml |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the user "dispatcher" is loaded:
      | email      | dispatcher@coopcycle.org |
      | password   | 123456            |
    And the user "dispatcher" has role "ROLE_DISPATCHER"
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/incidents" with body:
      """
      {
        "description": "Wrong order details",
        "failureReasonCode": "INCORRECT_ITEM",
        "task": "/api/tasks/2",
        "metadata": [
          {
            "suggestion": {
              "tasks": [
                {
                  "id": 2
                },
                {
                  "id": 1,
                  "packages": [
                    {"type": "XL", "quantity": 2}
                  ],
                  "weight": 30000
                }
              ]
            }
          }
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Incident",
        "@id":"@string@",
        "@type":"Incident",
        "id":@integer@,
        "title":"Article incorrect",
        "status":"OPEN",
        "priority":@integer@,
        "task":"/api/tasks/2",
        "failureReasonCode":"INCORRECT_ITEM",
        "description":"Wrong order details",
        "images":[],
        "events":[],
        "createdBy":"/api/users/2",
        "createdAt":"@string@.isDateTime()",
        "updatedAt":"@string@.isDateTime()",
        "tags":[],
        "metadata": [
          {
            "suggestion": {
              "id": 1,
              "tasks": [
                {
                  "id": 2
                },
                {
                  "id": 1,
                  "packages": [
                    {"type": "XL", "quantity": 2}
                  ],
                  "weight": 30000
                }
              ]
            }
          }
        ]
      }
      """
    And the database should contain an order with a total price 499
    Given the user "dispatcher" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "dispatcher" sends a "PUT" request to "/api/incidents/1/action" with body:
      """
      {
        "action": "rejected_suggestion"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Incident",
        "@id":"@string@",
        "@type":"Incident",
        "id":@integer@,
        "title":"Article incorrect",
        "status":"OPEN",
        "priority":@integer@,
        "task":"/api/tasks/2",
        "failureReasonCode":"INCORRECT_ITEM",
        "description":"Wrong order details",
        "images":[],
        "events":[
          {
            "@type": "IncidentEvent",
            "@id":"@string@",
            "id":@integer@,
            "type":"rejected_suggestion",
            "message":null,
            "metadata":{
              "diff": null
            },
            "createdBy":"/api/users/3",
            "createdAt":"@string@.isDateTime()"
          }
        ],
        "createdBy":"/api/users/2",
        "createdAt":"@string@.isDateTime()",
        "updatedAt":"@string@.isDateTime()",
        "tags":[],
        "metadata": [
          {
            "suggestion": {
              "id": 1,
              "tasks": [
                {
                  "id": 2
                },
                {
                  "id": 1,
                  "packages": [
                    {"type": "XL", "quantity": 2}
                  ],
                  "weight": 30000
                }
              ]
            }
          }
        ]
      }
      """
    And the database should contain an order with a total price 499

  Scenario: Report incident: pre-fill missing manual supplements in suggestion
    Given the fixtures files are loaded:
      | sylius_taxation.yml        |
      | payment_methods.yml        |
      | sylius_products.yml        |
      | store_with_manual_supplements_mixed.yml |
    And the setting "subject_to_vat" has value "1"
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    # First create a delivery with both fixed price and range-based manual supplements
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "store":"/api/stores/1",
        "pickup": {
          "address": "24, Rue de la Paix Paris",
          "doneBefore": "tomorrow 13:00",
          "comments": "#bob"
        },
        "dropoff": {
          "address": "48, Rue de Rivoli Paris",
          "doneBefore": "tomorrow 15:00"
        },
        "order": {
          "manualSupplements": [
            {
              "pricingRule": "/api/pricing_rules/2",
              "quantity": 1
            },
            {
              "pricingRule": "/api/pricing_rules/3",
              "quantity": 1
            },
            {
              "pricingRule": "/api/pricing_rules/4",
              "quantity": 15
            },
            {
              "pricingRule": "/api/pricing_rules/5",
              "quantity": 3
            }
          ]
        }
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the tasks with comments matching "#bob" are assigned to "bob"
    # Now create an incident with a suggestion that doesn't have manualSupplements
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/incidents" with body:
      """
      {
        "description": "Need to update delivery details",
        "failureReasonCode": "INCORRECT_ITEM",
        "task": "/api/tasks/2",
        "metadata": [
          {
            "suggestion": {
              "tasks": [
                {
                  "id": 2,
                  "packages": [
                    {"type": "XL", "quantity": 3}
                  ]
                }
              ]
            }
          }
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Incident",
        "@id":"@string@",
        "@type":"Incident",
        "id":@integer@,
        "title":"Article incorrect",
        "status":"OPEN",
        "priority":@integer@,
        "task":"/api/tasks/2",
        "failureReasonCode":"INCORRECT_ITEM",
        "description":"Need to update delivery details",
        "images":[],
        "events":[],
        "createdBy":"/api/users/2",
        "createdAt":"@string@.isDateTime()",
        "updatedAt":"@string@.isDateTime()",
        "tags":[],
        "metadata": [
          {
            "suggestion": {
              "id": 1,
              "tasks": [
                {
                  "id": 2,
                  "packages": [
                    {"type": "XL", "quantity": 3}
                  ]
                },
                {
                  "id": 1
                }
              ],
              "order": {
                "manualSupplements": [
                  {
                    "pricingRule": "/api/pricing_rules/2",
                    "quantity": 1
                  },
                  {
                    "pricingRule": "/api/pricing_rules/3",
                    "quantity": 1
                  },
                  {
                    "pricingRule": "/api/pricing_rules/4",
                    "quantity": 3
                  },
                  {
                    "pricingRule": "/api/pricing_rules/5",
                    "quantity": 1
                  }
                ]
              }
            }
          }
        ]
      }
      """

  Scenario: Report incident: keep empty manual supplements array in suggestion
    Given the fixtures files are loaded:
      | sylius_taxation.yml        |
      | payment_methods.yml        |
      | sylius_products.yml        |
      | store_with_manual_supplements_mixed.yml |
    And the setting "subject_to_vat" has value "1"
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    # First create a delivery with both fixed price and range-based manual supplements
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "store":"/api/stores/1",
        "pickup": {
          "address": "24, Rue de la Paix Paris",
          "doneBefore": "tomorrow 13:00",
          "comments": "#bob"
        },
        "dropoff": {
          "address": "48, Rue de Rivoli Paris",
          "doneBefore": "tomorrow 15:00"
        },
        "order": {
          "manualSupplements": [
            {
              "pricingRule": "/api/pricing_rules/2",
              "quantity": 1
            },
            {
              "pricingRule": "/api/pricing_rules/3",
              "quantity": 1
            },
            {
              "pricingRule": "/api/pricing_rules/4",
              "quantity": 15
            },
            {
              "pricingRule": "/api/pricing_rules/5",
              "quantity": 3
            }
          ]
        }
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the tasks with comments matching "#bob" are assigned to "bob"
    # Now create an incident with a suggestion that has an empty manualSupplements array
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/incidents" with body:
      """
      {
        "description": "Need to update delivery details without supplements",
        "failureReasonCode": "INCORRECT_ITEM",
        "task": "/api/tasks/2",
        "metadata": [
          {
            "suggestion": {
              "tasks": [
                {
                  "id": 2,
                  "packages": [
                    {"type": "XL", "quantity": 3}
                  ]
                }
              ],
              "order": {
                "manualSupplements": []
              }
            }
          }
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Incident",
        "@id":"/api/incidents/1",
        "@type":"Incident",
        "id":@integer@,
        "title":"Article incorrect",
        "status":"OPEN",
        "priority":@integer@,
        "task":"/api/tasks/2",
        "failureReasonCode":"INCORRECT_ITEM",
        "description":"Need to update delivery details without supplements",
        "images":[],
        "events":[],
        "createdBy":"/api/users/2",
        "createdAt":"@string@.isDateTime()",
        "updatedAt":"@string@.isDateTime()",
        "tags":[],
        "metadata": [
          {
            "suggestion": {
              "id": 1,
              "tasks": [
                {
                  "id": 2,
                  "packages": [
                    {"type": "XL", "quantity": 3}
                  ]
                },
                {
                  "id": 1
                }
              ],
              "order": {
                "manualSupplements": []
              }
            }
          }
        ]
      }
      """
    # Make sure that the store can retrieve incident via OAuth token
    Given the store with name "Store with Mixed Supplements" has an OAuth client named "Store with Mixed Supplements"
    And the OAuth client with name "Store with Mixed Supplements" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Store with Mixed Supplements" sends a "GET" request to "/api/incidents/1"
    Then the response status code should be 200


  Scenario: Report incident with image
    Given the fixtures files are loaded:
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    Given I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/incidents" with body:
      """
      {
        "description": "PACKAGE WET",
        "failureReasonCode": "DAMAGED",
        "task": "/api/tasks/2"
      }
      """
    Then the response status code should be 201
    Given I add "Content-Type" header equal to "multipart/form-data"
    And I add "X-Attach-To" header equal to "/api/incidents/1"
    And the user "bob" sends a "POST" request to "/api/incident_images" with parameters:
      | key      | value              |
      | file     | @beer.jpg |
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/IncidentImage",
        "@id":"/api/incident_images/1",
        "@type":"http://schema.org/MediaObject",
        "thumbnail":@string@
      }
      """
    Given I add "Content-Type" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/incidents/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Incident",
        "@id":"/api/incidents/1",
        "@type":"Incident",
        "id":@integer@,
        "title":@string@,
        "status":"OPEN",
        "priority":@integer@,
        "task":"/api/tasks/2",
        "failureReasonCode":"DAMAGED",
        "description":"PACKAGE WET",
        "images":[
          {
            "@id":"/api/incident_images/1",
            "@type":"http://schema.org/MediaObject",
            "id":1,
            "imageName":@string@,
            "thumbnail":@string@
          }
        ],
        "events":[],
        "createdBy":"/api/users/1",
        "metadata":[],
        "createdAt":"@string@.isDateTime()",
        "updatedAt":"@string@.isDateTime()",
        "tags":[]
      }
      """
