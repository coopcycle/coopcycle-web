Feature: Warehouses

  Scenario: Relay a delivery through a warehouse by selecting a single pickup
    Given the fixtures files are loaded:
      | warehouse_relay.yml |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/warehouses/1/relay" with body:
      """
      {
        "tasks": ["/api/tasks/1"]
      }
      """
    Then the response status code should be 201
    # The backend resolves the linked pair and returns the 2 original tasks
    # plus the 2 tasks created by the relay operation.
    And the JSON node "tasks" should have 4 elements
    And the JSON node "tasks[0].type" should be equal to "PICKUP"
    And the JSON node "tasks[1].type" should be equal to "DROPOFF"
    And the JSON node "tasks[2].type" should be equal to "DROPOFF"
    And the JSON node "tasks[3].type" should be equal to "PICKUP"
    # The 2 created hub tasks are located at the warehouse address.
    And the JSON node "tasks[2].address.streetAddress" should be equal to "17, rue Milton 75009 Paris 9ème"
    And the JSON node "tasks[3].address.streetAddress" should be equal to "17, rue Milton 75009 Paris 9ème"

  Scenario: Relay a delivery through a warehouse by selecting a single dropoff
    Given the fixtures files are loaded:
      | warehouse_relay.yml |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/warehouses/1/relay" with body:
      """
      {
        "tasks": ["/api/tasks/2"]
      }
      """
    Then the response status code should be 201
    And the JSON node "tasks" should have 4 elements
    And the JSON node "tasks[0].type" should be equal to "PICKUP"
    And the JSON node "tasks[1].type" should be equal to "DROPOFF"

  Scenario: Relay a delivery through a warehouse by selecting both linked tasks
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
    # Selecting the pickup and its dropoff still yields a single relay operation.
    And the JSON node "tasks" should have 4 elements

  Scenario: Relay multiple deliveries through a warehouse at once
    Given the fixtures files are loaded:
      | warehouse_relay.yml |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/warehouses/1/relay" with body:
      """
      {
        "tasks": ["/api/tasks/1", "/api/tasks/3"]
      }
      """
    Then the response status code should be 201
    # Two distinct pairs, each relayed: 2 originals + 2 created per pair.
    And the JSON node "tasks" should have 8 elements

  Scenario: Relay tasks through a warehouse - hub tasks copy the surrounding time window
    Given the fixtures files are loaded:
      | warehouse_relay.yml |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/warehouses/1/relay" with body:
      """
      {
        "tasks": ["/api/tasks/1"]
      }
      """
    Then the response status code should be 201
    # There is a gap between the pickup end (12:00) and the dropoff start (14:00),
    # so the hub tasks share that window.
    And the JSON node "tasks[2].doneAfter" should contain "2018-12-01T12:00:00"
    And the JSON node "tasks[2].doneBefore" should contain "2018-12-01T14:00:00"
    And the JSON node "tasks[3].doneAfter" should contain "2018-12-01T12:00:00"
    And the JSON node "tasks[3].doneBefore" should contain "2018-12-01T14:00:00"

  Scenario: Relay tasks through a warehouse requires authentication
    Given the fixtures files are loaded:
      | warehouse_relay.yml |
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "POST" request to "/api/warehouses/1/relay" with body:
      """
      {
        "tasks": ["/api/tasks/1"]
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
        "tasks": ["/api/tasks/1"]
      }
      """
    Then the response status code should be 403

  Scenario: Relay tasks through a warehouse with an empty task list
    Given the fixtures files are loaded:
      | warehouse_relay.yml |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/warehouses/1/relay" with body:
      """
      {
        "tasks": []
      }
      """
    Then the response status code should be 400

  Scenario: Relay a task that has no linked pickup/dropoff pair
    Given the fixtures files are loaded:
      | warehouse_relay.yml |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/warehouses/1/relay" with body:
      """
      {
        "tasks": ["/api/tasks/5"]
      }
      """
    Then the response status code should be 400
