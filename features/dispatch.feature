Feature: Dispatch

  Scenario: Not authorized to list task lists
    Given the fixtures files are loaded:
      | dispatch.yml        |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/task_lists"
    Then the response status code should be 403

  Scenario: Not authorized to retrieve task list
    Given the fixtures files are loaded:
      | dispatch.yml        |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/task_lists/1"
    Then the response status code should be 403

  Scenario: Retrieve task lists
    Given the fixtures files are loaded:
      | dispatch.yml        |
    And the user "sarah" has role "ROLE_COURIER"
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/task_lists?date=2018-12-01"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/TaskList",
        "@id":"/api/task_lists",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"@string@.startsWith('/api/task_lists')",
            "@type":"TaskList",
            "items":@array@,
            "distance":0,
            "duration":0,
            "polyline":"",
            "createdAt":"@string@.isDateTime()",
            "updatedAt":"@string@.isDateTime()",
            "username":"sarah",
            "date":"2018-12-01"
          },
          {
            "@id":"@string@.startsWith('/api/task_lists')",
            "@type":"TaskList",
            "items":@array@,
            "distance":0,
            "duration":0,
            "polyline":"",
            "createdAt":"@string@.isDateTime()",
            "updatedAt":"@string@.isDateTime()",
            "username":"bob",
            "date":"2018-12-01"
          }
        ],
        "hydra:totalItems":2,
        "hydra:view":{
          "@id":"/api/task_lists?date=2018-12-01",
          "@type":"hydra:PartialCollectionView"
        },
        "hydra:search":{
          "@type":"hydra:IriTemplate",
          "hydra:template":"/api/task_lists{?date}",
          "hydra:variableRepresentation":"BasicRepresentation",
          "hydra:mapping":@array@
        }
      }
      """

  Scenario: Create task list
    Given the fixtures files are loaded:
      | dispatch.yml        |
    And the user "sarah" has role "ROLE_COURIER"
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists" with body:
      """
      {
        "date": "2018-12-03",
        "courier": "/api/users/2"
      }
      """
    Then the response status code should be 201

  Scenario: Create task list already existing
    Given the fixtures files are loaded:
      | dispatch.yml        |
    And the user "sarah" has role "ROLE_COURIER"
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_lists" with body:
      """
      {
        "date": "2018-12-02",
        "courier": "/api/users/1"
      }
      """
    Then the response status code should be 201

  Scenario: Administrator can assign task
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | dispatch.yml        |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/tasks/4/assign" with body:
      """
      {
        "username": "sarah"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"/api/tasks/4",
        "@type":"Task",
        "id":4,
        "type":"PICKUP",
        "status":"TODO",
        "address":@...@,
        "doneAfter":"@string@.isDateTime()",
        "doneBefore":"@string@.isDateTime()",
        "comments":null,
        "updatedAt":"@string@.isDateTime()",
        "isAssigned":true,
        "assignedTo":"sarah",
        "previous":null,
        "next":null,
        "group":null,
        "tags":@array@
      }
      """

  Scenario: Courier can self-assign task
    Given the fixtures files are loaded:
      | dispatch.yml        |
    And the user "sarah" has role "ROLE_COURIER"
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/tasks/6/assign" with body:
      """
      {}
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"/api/tasks/6",
        "@type":"Task",
        "id":6,
        "type":"DROPOFF",
        "status":"TODO",
        "address":@...@,
        "doneAfter":"@string@.isDateTime()",
        "doneBefore":"@string@.isDateTime()",
        "comments":null,
        "updatedAt":"@string@.isDateTime()",
        "isAssigned":true,
        "assignedTo":"sarah",
        "previous":null,
        "next":null,
        "group":null,
        "tags":@array@
      }
      """

  Scenario: Courier can't self-assign task already assigned to someone else
    Given the fixtures files are loaded:
      | dispatch.yml        |
    And the user "sarah" has role "ROLE_COURIER"
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/tasks/4/assign" with body:
      """
      {}
      """
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Error",
        "@type":"hydra:Error",
        "hydra:title":"An error occurred",
        "hydra:description":"Task #4 is already assigned to \u0022sarah\u0022",
        "trace":@array@
      }
      """

  Scenario: Administrator can self-assign task already assigned to someone else
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | dispatch.yml        |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/tasks/4/assign" with body:
      """
      {}
      """
    Then the response status code should be 200
    And the response should be in JSON

  Scenario: Courier can unassign task assigned to him/her
    Given the fixtures files are loaded:
      | dispatch.yml        |
    And the user "sarah" has role "ROLE_COURIER"
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/tasks/1/unassign" with body:
      """
      {}
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"/api/tasks/1",
        "@type":"Task",
        "id":1,
        "type":"DROPOFF",
        "status":"TODO",
        "address":@...@,
        "doneAfter":"@string@.isDateTime()",
        "doneBefore":"@string@.isDateTime()",
        "comments":null,
        "updatedAt":"@string@.isDateTime()",
        "isAssigned":false,
        "assignedTo":null,
        "previous":null,
        "next":null,
        "group":null,
        "tags":@array@
      }
      """

  Scenario: Courier can't unassign task not assigned to him/her
    Given the fixtures files are loaded:
      | dispatch.yml        |
    And the user "sarah" has role "ROLE_COURIER"
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/tasks/3/unassign" with body:
      """
      {}
      """
    Then the response status code should be 403
    And the response should be in JSON

  Scenario: Get optimized task list
    Given the fixtures files are loaded:
      | dispatch.yml        |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/task_lists/1/optimize"
    Then the response status code should be 200
    And the response should be in JSON
