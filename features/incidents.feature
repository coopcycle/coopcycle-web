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
        "metadata": {"@*@": "@*@"}
      }
      """

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
                  "id": 1
                },
                {
                  "id": 2,
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
              "tasks": [
                {
                  "id": 1
                },
                {
                  "id": 2,
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
              "id": 1,
              "tasks": [
                {
                  "id": 1
                },
                {
                  "id": 2,
                  "packages": [
                    {"type": "XL", "quantity": 2}
                  ],
                  "weight": 30000
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
                  "id": 1
                },
                {
                  "id": 2,
                  "packages": [
                    {"type": "XL", "quantity": 2}
                  ],
                  "weight": 30000
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
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/ConstraintViolationList",
        "@type": "ConstraintViolationList",
        "hydra:title": "An error occurred",
        "hydra:description": "metadata[0][suggestion]: The suggestion field in metadata is not a valid delivery",
        "violations": [
          {
            "propertyPath": "metadata[0][suggestion]",
            "message": "The suggestion field in metadata is not a valid delivery",
            "code": null
          }
        ]
      }
      """
