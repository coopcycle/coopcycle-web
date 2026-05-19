Feature: Warehouses

  Scenario: Relay tasks through a warehouse
    Given the fixtures files are loaded:
      | warehouse_relay.yml |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/warehouses/1/relay" with body:
      """
      {
        "tasks": ["/api/tasks/1", "/api/tasks/2"]
      }
      """
    Then the response status code should be 201
    And the JSON should match:
      """
      {
        "hubDropoff": {
          "@type": "Task",
          "type": "DROPOFF",
          "status": "TODO",
          "address": {
            "streetAddress": "17, rue Milton 75009 Paris 9ème",
            "@*@": "@*@"
          },
          "after": "@string@.isDateTime()",
          "before": "@string@.isDateTime()",
          "@*@": "@*@"
        },
        "hubPickup": {
          "@type": "Task",
          "type": "PICKUP",
          "status": "TODO",
          "address": {
            "streetAddress": "17, rue Milton 75009 Paris 9ème",
            "@*@": "@*@"
          },
          "after": "@string@.isDateTime()",
          "before": "@string@.isDateTime()",
          "@*@": "@*@"
        }
      }
      """

  Scenario: Relay tasks through a warehouse - hub dropoff copies pickup time window
    Given the fixtures files are loaded:
      | warehouse_relay.yml |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/warehouses/1/relay" with body:
      """
      {
        "tasks": ["/api/tasks/1", "/api/tasks/2"]
      }
      """
    Then the response status code should be 201
    And the JSON node "hubDropoff.doneAfter" should contain "2018-12-01T12:00:00"
    And the JSON node "hubDropoff.doneBefore" should contain "2018-12-01T14:00:00"
    And the JSON node "hubPickup.doneAfter" should contain "2018-12-01T12:00:00"
    And the JSON node "hubPickup.doneBefore" should contain "2018-12-01T14:00:00"

  Scenario: Relay tasks through a warehouse requires authentication
    Given the fixtures files are loaded:
      | warehouse_relay.yml |
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "POST" request to "/api/warehouses/1/relay" with body:
      """
      {
        "tasks": ["/api/tasks/1", "/api/tasks/2"]
      }
      """
    Then the response status code should be 401

  Scenario: Relay tasks through a warehouse requires ROLE_DISPATCHER
    Given the fixtures files are loaded:
      | warehouse_relay.yml |
    And the user "bob" has role "ROLE_COURIER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/warehouses/1/relay" with body:
      """
      {
        "tasks": ["/api/tasks/1", "/api/tasks/2"]
      }
      """
    Then the response status code should be 403

  Scenario: Relay tasks through a warehouse with invalid task types
    Given the fixtures files are loaded:
      | warehouse_relay.yml |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/warehouses/1/relay" with body:
      """
      {
        "tasks": ["/api/tasks/1", "/api/tasks/1"]
      }
      """
    Then the response status code should be 400
