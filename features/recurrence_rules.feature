Feature: Task recurrence rules

  Scenario: Create recurrence rule (single task, existing address)
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | users.yml           |
      | stores.yml          |
      | addresses.yml       |
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
          "@type":"Task",
          "address": "/api/addresses/1",
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
        "@id":@string@,
        "@type":"RecurrenceRule",
        "store":"/api/stores/1",
        "orgName":"Acme",
        "rule":"FREQ=WEEKLY",
        "template":{
          "@type":"Task",
          "address": {
            "@id": @string@,
            "streetAddress": @string@
          },
          "after":"11:30",
          "before":"12:00"
        }
      }
      """

  Scenario: Create recurrence rule (single task, new address)
    Given the fixtures files are loaded:
      | sylius_channels.yml |
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
        "rule":"FREQ=WEEKLY",
        "template":{
          "@type":"Task",
          "address": {
            "@id": @string@,
            "streetAddress": @string@
          },
          "after":"11:30",
          "before":"12:00"
        }
      }
      """

  Scenario: Create recurrence rule (multiple tasks, existing address)
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | users.yml           |
      | stores.yml          |
      | addresses.yml       |
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
              "address": "/api/addresses/1",
              "after":"11:30",
              "before":"12:00"
            },
            {
              "@type":"Task",
              "address": "/api/addresses/2",
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
        "store":"/api/stores/1",
        "orgName":"Acme",
        "rule":"FREQ=WEEKLY",
        "template": {
          "@type":"hydra:Collection",
          "hydra:member": [
            {
              "@type":"Task",
              "address": {
                "@id": @string@,
                "streetAddress": @string@
              },
              "after":"11:30",
              "before":"12:00"
            },
            {
              "@type":"Task",
              "address": {
                "@id": @string@,
                "streetAddress": @string@
              },
              "after":"12:00",
              "before":"12:30"
            }
          ]
        }
      }
      """

  Scenario: Create recurrence rule (multiple tasks, new address)
    Given the fixtures files are loaded:
      | sylius_channels.yml |
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
                "streetAddress": "1, Rue de Rivoli, 75004 Paris"
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
        "template": {
          "@type":"hydra:Collection",
          "hydra:member": [
            {
              "@type":"Task",
              "address": {
                "@id": @string@,
                "streetAddress": @string@
              },
              "after":"11:30",
              "before":"12:00"
            },
            {
              "@type":"Task",
              "address": {
                "@id": @string@,
                "streetAddress": @string@
              },
              "after":"12:00",
              "before":"12:30"
            }
          ]
        }
      }
      """

  Scenario: Update recurrence rule (single task, existing address)
    Given the fixtures files are loaded:
      | sylius_channels.yml  |
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
          "address": "/api/addresses/2",
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
        "rule":"FREQ=WEEKLY",
        "store":"/api/stores/1",
        "orgName":"Acme",
        "template":{
          "@type":"Task",
          "address": {
            "@id": "/api/addresses/2",
            "streetAddress": @string@
          },
          "after":"11:30",
          "before":"12:30"
        }
      }
      """

  Scenario: Update recurrence rule (single task, new address)
    Given the fixtures files are loaded:
      | sylius_channels.yml  |
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
        "rule":"FREQ=WEEKLY",
        "template":{
          "@type":"Task",
          "address": {
            "@id": @string@,
            "streetAddress": @string@
          },
          "after":"11:30",
          "before":"12:30"
        }
      }
      """

  Scenario: List recurrence rules
    Given the fixtures files are loaded:
      | sylius_channels.yml  |
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
        "hydra:totalItems":2
      }
      """

  Scenario: Get soft deleted recurrence rules
    Given the fixtures files are loaded:
      | sylius_channels.yml  |
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
      | sylius_channels.yml  |
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
      | sylius_channels.yml  |
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
        "@id":"/api/recurrence_rules",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/tasks/1",
            "@type":"Task"
          },
          {
            "@id":"/api/tasks/2",
            "@type":"Task"
          }
        ],
        "hydra:totalItems":2
      }
      """
