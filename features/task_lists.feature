Feature: Tasks lists
  Scenario: Assign tasks and tours with PUT
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml        |
      | users.yml        |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/task_lists/set_items/2018-03-02/bob" with body:
    """
    {"items" : ["/api/tasks/4", "/api/tours/1"]}
    """
    Then the response status code should be 200
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/TaskList",
      "@id":"/api/task_lists/1",
      "id": "@integer@",
      "@type":"TaskList",
      "items":["/api/tasks/4","/api/tours/1"],
      "distance":@integer@,
      "duration":@integer@,
      "polyline":"@string@",
      "createdAt":"@string@.isDateTime()",
      "updatedAt":"@string@.isDateTime()",
      "date":"2018-03-02",
      "username":"bob",
      "vehicle": null,
      "trailer": null
    }
    """

  Scenario: Assign task and tour with PUT then remove the task
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml        |
      | users.yml        |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/task_lists/set_items/2018-03-02/bob" with body:
    """
    {"items" : ["/api/tasks/4", "/api/tours/1"]}
    """
    Then the response status code should be 200
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/TaskList",
      "@id":"/api/task_lists/1",
      "id": "@integer@",
      "@type":"TaskList",
      "items":["/api/tasks/4", "/api/tours/1"],
      "distance":@integer@,
      "duration":@integer@,
      "polyline":"@string@",
      "createdAt":"@string@.isDateTime()",
      "updatedAt":"@string@.isDateTime()",
      "date":"2018-03-02",
      "username":"bob",
      "vehicle": null,
      "trailer": null
    }
    """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/task_lists/set_items/2018-03-02/bob" with body:
    """
    {"items" : ["/api/tours/1"]}
    """
    Then the response status code should be 200
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/TaskList",
      "@id":"/api/task_lists/1",
      "id": "@integer@",
      "@type":"TaskList",
      "items":["/api/tours/1"],
      "distance":@integer@,
      "duration":@integer@,
      "polyline":"@string@",
      "createdAt":"@string@.isDateTime()",
      "updatedAt":"@string@.isDateTime()",
      "date":"2018-03-02",
      "username":"bob",
      "vehicle": null,
      "trailer": null
    }
    """

  Scenario: Assign a bunch of tasks and reorder them
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml        |
      | users.yml        |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/task_lists/set_items/2018-03-02/bob" with body:
    """
    {"items" : ["/api/tasks/4", "/api/tasks/5", "/api/tasks/6", "/api/tasks/7"]}
    """
    Then the response status code should be 200
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/TaskList",
      "@id":"/api/task_lists/1",
      "id": "@integer@",
      "@type":"TaskList",
      "items":["/api/tasks/4", "/api/tasks/5", "/api/tasks/6", "/api/tasks/7"],
      "distance":@integer@,
      "duration":@integer@,
      "polyline":"@string@",
      "createdAt":"@string@.isDateTime()",
      "updatedAt":"@string@.isDateTime()",
      "date":"2018-03-02",
      "username":"bob",
      "vehicle": null,
      "trailer": null
    }
    """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/task_lists/set_items/2018-03-02/bob" with body:
    """
    {"items" : ["/api/tasks/7", "/api/tasks/4", "/api/tasks/6", "/api/tasks/5"]}
    """
    Then the response status code should be 200
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/TaskList",
      "@id":"/api/task_lists/1",
      "id": "@integer@",
      "@type":"TaskList",
      "items":["/api/tasks/7", "/api/tasks/4", "/api/tasks/6", "/api/tasks/5"],
      "distance":@integer@,
      "duration":@integer@,
      "polyline":"@string@",
      "createdAt":"@string@.isDateTime()",
      "updatedAt":"@string@.isDateTime()",
      "date":"2018-03-02",
      "username":"bob",
      "vehicle": null,
      "trailer": null
    }
    """

   Scenario: Assign task with PUT then add a tour to the tasklist
    Given the fixtures files are loaded:
      | sylius_channels.yml |
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
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/TaskList",
      "id": "@integer@",
      "@id":"/api/task_lists/1",
      "@type":"TaskList",
      "items":["/api/tasks/4"],
      "distance":@integer@,
      "duration":@integer@,
      "polyline":"@string@",
      "createdAt":"@string@.isDateTime()",
      "updatedAt":"@string@.isDateTime()",
      "date":"2018-03-02",
      "username":"bob",
      "vehicle": null,
      "trailer": null
    }
    """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/task_lists/set_items/2018-03-02/bob" with body:
    """
    {"items" : ["/api/tasks/4", "/api/tours/1"]}
    """
    Then the response status code should be 200
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/TaskList",
      "@id":"/api/task_lists/1",
      "id": "@integer@",
      "@type":"TaskList",
      "items":["/api/tasks/4","/api/tours/1"],
      "distance":@integer@,
      "duration":@integer@,
      "polyline":"@string@",
      "createdAt":"@string@.isDateTime()",
      "updatedAt":"@string@.isDateTime()",
      "date":"2018-03-02",
      "username":"bob",
      "vehicle": null,
      "trailer": null
    }
    """

   Scenario: Assign a tour with PUT, then add a task to the tour -> task.assignedTo is set correctly
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml        |
      | users.yml        |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/task_lists/set_items/2018-03-02/bob" with body:
    """
    {"items" : ["/api/tours/1"]}
    """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/tours/1" with body:
    """
    {"name": "Example tour", "tasks" : ["/api/tasks/4"]}
    """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/tasks/4"
    Then the response status code should be 200
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/Task",
      "@id":"/api/tasks/4",
      "assignedTo": "bob",
      "@*@": "@*@"
    }
    """
