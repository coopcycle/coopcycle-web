Feature: Task list items
  # Putting work on a courier's list, and taking it off, by naming what
  # changes. The whole-list PUT these replace cannot tell a deliberate removal
  # from a client that never heard of the item, because both leave it out.
  #
  # See https://github.com/coopcycle/coopcycle-web/issues/5372

  Scenario: Open a task list for a courier
    Given the fixtures files are loaded:
      | tasks.yml        |
      | users.yml        |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists" with body:
    """
    {"courier": "/api/users/1", "date": "2018-03-02"}
    """
    Then the response status code should be 201
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/TaskList",
      "@id":"/api/task_lists/1",
      "id": "@integer@",
      "@type":"TaskList",
      "version":1,
      "items":[],
      "distance":@integer@,
      "duration":@integer@,
      "polyline":"@string@",
      "createdAt":"@string@.isDateTime()",
      "updatedAt":"@string@.isDateTime()",
      "date":"2018-03-02",
      "username":"bob",
      "color": "#867d2d",
      "vehicle": null,
      "trailer": null
    }
    """

  Scenario: Opening a task list that already exists hands back the same one
    Given the fixtures files are loaded:
      | tasks.yml        |
      | users.yml        |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists" with body:
    """
    {"courier": "/api/users/1", "date": "2018-03-02"}
    """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists" with body:
    """
    {"courier": "/api/users/1", "date": "2018-03-02"}
    """
    Then the response status code should be 201
    And the JSON node "@id" should be equal to "/api/task_lists/1"

  Scenario: Put a task and a tour on a list
    Given the fixtures files are loaded:
      | tasks.yml        |
      | users.yml        |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists" with body:
    """
    {"courier": "/api/users/1", "date": "2018-03-02"}
    """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists/1/items" with body:
    """
    {"items": ["/api/tasks/4", "/api/tours/1"]}
    """
    Then the response status code should be 201
    And the JSON node "items" should have 2 elements
    And the JSON node "items[0]" should be equal to "/api/tasks/4"
    And the JSON node "items[1]" should be equal to "/api/tours/1"

  Scenario: Put a task at a given position, leaving the rest of the list alone
    Given the fixtures files are loaded:
      | tasks.yml        |
      | users.yml        |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists" with body:
    """
    {"courier": "/api/users/1", "date": "2018-03-02"}
    """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists/1/items" with body:
    """
    {"items": ["/api/tasks/4", "/api/tasks/5"]}
    """
    Then the response status code should be 201
    # Nothing is said about tasks 4 and 5, so nothing happens to them
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists/1/items" with body:
    """
    {"items": ["/api/tours/1"], "position": 1}
    """
    Then the response status code should be 201
    And the JSON node "items" should have 3 elements
    And the JSON node "items[0]" should be equal to "/api/tasks/4"
    And the JSON node "items[1]" should be equal to "/api/tours/1"
    And the JSON node "items[2]" should be equal to "/api/tasks/5"

  Scenario: Naming an item already on the list moves it
    Given the fixtures files are loaded:
      | tasks.yml        |
      | users.yml        |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists" with body:
    """
    {"courier": "/api/users/1", "date": "2018-03-02"}
    """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists/1/items" with body:
    """
    {"items": ["/api/tasks/4", "/api/tasks/5"]}
    """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists/1/items" with body:
    """
    {"items": ["/api/tasks/5"], "position": 0}
    """
    Then the response status code should be 201
    And the JSON node "items" should have 2 elements
    And the JSON node "items[0]" should be equal to "/api/tasks/5"
    And the JSON node "items[1]" should be equal to "/api/tasks/4"

  Scenario: Take items off a list, which unassigns them
    Given the fixtures files are loaded:
      | tasks.yml        |
      | users.yml        |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists" with body:
    """
    {"courier": "/api/users/1", "date": "2018-03-02"}
    """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists/1/items" with body:
    """
    {"items": ["/api/tasks/4", "/api/tasks/5", "/api/tours/1"]}
    """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists/1/items/remove" with body:
    """
    {"items": ["/api/tasks/4", "/api/tours/1"]}
    """
    Then the response status code should be 200
    And the JSON node "items" should have 1 element
    And the JSON node "items[0]" should be equal to "/api/tasks/5"
    When I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/tasks/4"
    Then the response status code should be 200
    And the JSON node "isAssigned" should be false

  Scenario: Take one item off a list
    Given the fixtures files are loaded:
      | tasks.yml        |
      | users.yml        |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists" with body:
    """
    {"courier": "/api/users/1", "date": "2018-03-02"}
    """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists/1/items" with body:
    """
    {"items": ["/api/tasks/4", "/api/tasks/5"]}
    """
    Then the response status code should be 201
    When I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "DELETE" request to "/api/task_lists/1/items/tasks/4"
    Then the response status code should be 204
    When I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/task_lists/1"
    Then the response status code should be 200
    And the JSON node "items" should have 1 element
    And the JSON node "items[0]" should be equal to "/api/tasks/5"

  Scenario: Taking off an item that is not on the list is not an error
    Given the fixtures files are loaded:
      | tasks.yml        |
      | users.yml        |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists" with body:
    """
    {"courier": "/api/users/1", "date": "2018-03-02"}
    """
    Then the response status code should be 201
    When I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "DELETE" request to "/api/task_lists/1/items/tasks/4"
    Then the response status code should be 204

  Scenario: Reorder a list, naming the version it was read at
    Given the fixtures files are loaded:
      | tasks.yml        |
      | users.yml        |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists" with body:
    """
    {"courier": "/api/users/1", "date": "2018-03-02"}
    """
    Then the response status code should be 201
    And the JSON node "version" should be equal to 1
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists/1/items" with body:
    """
    {"items": ["/api/tasks/4", "/api/tasks/5"]}
    """
    Then the response status code should be 201
    And the JSON node "version" should be equal to 2
    # The version just read goes back as If-Match: "2"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I add an If-Match header for version 2
    And the user "bob" sends a "PUT" request to "/api/task_lists/1/order" with body:
    """
    {"items": ["/api/tasks/5", "/api/tasks/4"]}
    """
    Then the response status code should be 200
    And the JSON node "items[0]" should be equal to "/api/tasks/5"
    And the JSON node "items[1]" should be equal to "/api/tasks/4"

  Scenario: Reordering refuses to change which items are on the list
    Given the fixtures files are loaded:
      | tasks.yml        |
      | users.yml        |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists" with body:
    """
    {"courier": "/api/users/1", "date": "2018-03-02"}
    """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists/1/items" with body:
    """
    {"items": ["/api/tasks/4", "/api/tasks/5"]}
    """
    Then the response status code should be 201
    # Leaving task 5 out would unassign it, which is what this endpoint exists
    # not to do: use the items endpoints to change membership
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I add an If-Match header for version 2
    And the user "bob" sends a "PUT" request to "/api/task_lists/1/order" with body:
    """
    {"items": ["/api/tasks/4"]}
    """
    Then the response status code should be 422

  Scenario: Reordering without naming a version is refused
    Given the fixtures files are loaded:
      | tasks.yml        |
      | users.yml        |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists" with body:
    """
    {"courier": "/api/users/1", "date": "2018-03-02"}
    """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/task_lists/1/order" with body:
    """
    {"items": []}
    """
    Then the response status code should be 428

  Scenario: Reordering against a version somebody else has moved past is refused
    Given the fixtures files are loaded:
      | tasks.yml        |
      | users.yml        |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists" with body:
    """
    {"courier": "/api/users/1", "date": "2018-03-02"}
    """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists/1/items" with body:
    """
    {"items": ["/api/tasks/4", "/api/tasks/5"]}
    """
    Then the response status code should be 201
    # Version 1 was read before the items were put on, so this order was built
    # against a list that no longer exists
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I add an If-Match header for version 1
    And the user "bob" sends a "PUT" request to "/api/task_lists/1/order" with body:
    """
    {"items": ["/api/tasks/5", "/api/tasks/4"]}
    """
    Then the response status code should be 409

  Scenario: The whole-list PUT still works, and says it is going away
    Given the fixtures files are loaded:
      | tasks.yml        |
      | users.yml        |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/task_lists/set_items/2018-03-02/bob" with body:
    """
    {"items" : ["/api/tasks/4"]}
    """
    Then the response status code should be 200
    And the header "Sunset" should contain "01 Oct 2027"
    And the header "Deprecation" should match "/^@[0-9]+$/"
    And the header "Link" should contain "successor-version"
    And the header "Link" should contain "/api/task_lists/{id}/items"
