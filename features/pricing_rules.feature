Feature: Pricing rules

  Scenario: Evaluate pricing rule (JWT)
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/pricing_rules/1/evaluate" with body:
      """
      {
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
        "@context":{
          "@vocab":@string@,
          "hydra":@string@,
          "result":"YesNoOutput/result"
        },
        "@type":"YesNoOutput",
        "@id":@string@,
        "result":true
      }
      """

  Scenario: Evaluate pricing rule (JWT)
    Given the current time is "2020-06-09 12:00:00"
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/pricing_rules/2/evaluate" with body:
      """
      {
        "pickup": {
          "address": {
            "streetAddress": "24, Rue de la Paix Paris",
            "latLng": [48.870134, 2.332221]
          },
          "timeSlot": "2020-06-09 17:00-18:00"
        },
        "dropoff": {
          "address": {
            "streetAddress": "48, Rue de Rivoli Paris",
            "latLng": [48.857127, 2.354766]
          },
          "timeSlot": "2020-06-09 17:00-18:00"
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":{
          "@vocab":@string@,
          "hydra":@string@,
          "result":"YesNoOutput/result"
        },
        "@type":"YesNoOutput",
        "@id":@string@,
        "result":false
      }
      """

  Scenario: Evaluate pricing rule (JWT)
    Given the current time is "2020-06-09 12:00:00"
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/pricing_rules/3/evaluate" with body:
      """
      {
        "packages": [
          {"type": "XL", "quantity": 2}
        ],
        "pickup": {
          "address": {
            "streetAddress": "24, Rue de la Paix Paris",
            "latLng": [48.870134, 2.332221]
          },
          "timeSlot": "2020-06-09 17:00-18:00"
        },
        "dropoff": {
          "address": {
            "streetAddress": "48, Rue de Rivoli Paris",
            "latLng": [48.857127, 2.354766]
          },
          "timeSlot": "2020-06-09 17:00-18:00"
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":{
          "@vocab":@string@,
          "hydra":@string@,
          "result":"YesNoOutput/result"
        },
        "@type":"YesNoOutput",
        "@id":@string@,
        "result":true
      }
      """

  Scenario: Evaluate pricing rule with packages in task (JWT)
    Given the current time is "2020-06-09 12:00:00"
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/pricing_rules/3/evaluate" with body:
      """
      {
        "pickup": {
          "address": {
            "streetAddress": "24, Rue de la Paix Paris",
            "latLng": [48.870134, 2.332221]
          },
          "timeSlot": "2020-06-09 17:00-18:00"
        },
        "dropoff": {
          "address": {
            "streetAddress": "48, Rue de Rivoli Paris",
            "latLng": [48.857127, 2.354766]
          },
          "timeSlot": "2020-06-09 17:00-18:00",
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
        "@context":{
          "@vocab":@string@,
          "hydra":@string@,
          "result":"YesNoOutput/result"
        },
        "@type":"YesNoOutput",
        "@id":@string@,
        "result":true
      }
      """

  Scenario: Evaluate pricing rule with weight in task (JWT)
    Given the current time is "2020-06-09 12:00:00"
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/pricing_rules/4/evaluate" with body:
      """
      {
        "pickup": {
          "address": {
            "streetAddress": "24, Rue de la Paix Paris",
            "latLng": [48.870134, 2.332221]
          },
          "timeSlot": "2020-06-09 17:00-18:00"
        },
        "dropoff": {
          "address": {
            "streetAddress": "48, Rue de Rivoli Paris",
            "latLng": [48.857127, 2.354766]
          },
          "timeSlot": "2020-06-09 17:00-18:00",
          "weight": 1500
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":{
          "@vocab":@string@,
          "hydra":@string@,
          "result":"YesNoOutput/result"
        },
        "@type":"YesNoOutput",
        "@id":@string@,
        "result":true
      }
      """

  Scenario: Pricing rule with weight in multiple dropoff tasks(JWT)
    Given the current time is "2020-06-09 12:00:00"
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/pricing_rules/4/evaluate" with body:
      """
      {
        "tasks": [
          {
            "type": "pickup",
            "address": {
              "streetAddress": "24, Rue de la Paix Paris",
              "latLng": [48.870134, 2.332221]
            },
            "timeSlot": "2020-06-09 17:00-18:00"
          },
          {
            "type": "dropoff",
            "address": {
              "streetAddress": "48, Rue de Rivoli Paris",
              "latLng": [48.857127, 2.354766]
            },
            "timeSlot": "2020-06-09 17:00-18:00",
            "packages": [
              {"type": "XL", "quantity": 2}
            ],
            "weight": 1000
          },
          {
            "type": "dropoff",
            "address": {
              "streetAddress": "48, Rue de Rivoli Paris",
              "latLng": [48.857127, 2.354766]
            },
            "timeSlot": "2020-06-09 17:00-18:00",
            "packages": [
              {"type": "XL", "quantity": 2}
            ],
            "weight": 2000
          }
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":{
          "@vocab":@string@,
          "hydra":@string@,
          "result":"YesNoOutput/result"
        },
        "@type":"YesNoOutput",
        "@id":@string@,
        "result":false
      }
      """

  Scenario: Evaluate pricing rule with weight in multiple dropoff tasks (JWT)
    Given the current time is "2020-06-09 12:00:00"
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/pricing_rules/4/evaluate" with body:
      """
      {
        "tasks": [
          {
            "type": "pickup",
            "address": {
              "streetAddress": "24, Rue de la Paix Paris",
              "latLng": [48.870134, 2.332221]
            },
            "timeSlot": "2020-06-09 17:00-18:00"
          },
          {
            "type": "dropoff",
            "address": {
              "streetAddress": "48, Rue de Rivoli Paris",
              "latLng": [48.857127, 2.354766]
            },
            "timeSlot": "2020-06-09 17:00-18:00",
            "weight": 1000
          },
          {
            "type": "dropoff",
            "address": {
              "streetAddress": "48, Rue de Rivoli Paris",
              "latLng": [48.857127, 2.354766]
            },
            "timeSlot": "2020-06-09 17:00-18:00",
            "weight": 800
          }
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":{
          "@vocab":@string@,
          "hydra":@string@,
          "result":"YesNoOutput/result"
        },
        "@type":"YesNoOutput",
        "@id":@string@,
        "result":true
      }
      """
