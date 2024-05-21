Feature: Tasks lists
  Scenario: Assign tasks and tours with PUT and retrieve tasks in the app
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
      "@type":"TaskList",
      "items":["/api/tasks/4","/api/tours/1"],
      "distance":@integer@,
      "duration":@integer@,
      "polyline":"@string@",
      "createdAt":"@string@.isDateTime()",
      "updatedAt":"@string@.isDateTime()",
      "date":"2018-03-02",
      "username":"bob"
    }
    """
    And the user "bob" sends a "GET" request to "/api/me/tasks/2018-03-02"
    Then print last JSON response
    Then the response status code should be 200
    Then print last JSON response
    And the response should be in JSON
    And the JSON should match:
    """
      {
        "@context":"/api/contexts/TaskList",
        "@id":"/api/task_lists/1",
        "@type":"TaskList",
        "distance":@integer@,
        "duration":@integer@,
        "polyline":@string@,
        "date":"2018-03-02",
        "username":"bob",
        "createdAt":"@string@.isDateTime()",
        "updatedAt":"@string@.isDateTime()",
        "hydra:member":[
          {
            "@id":"@string@.startsWith('/api/tasks')",
            "@context": "/api/contexts/Task",
            "@type":"Task",
            "id":4,
            "type":"DROPOFF",
            "status":"TODO",
            "address":{"@*@":"@*@"},
            "after":"@string@.isDateTime().startsWith('2018-03-02T11:30:00')",
            "before":"@string@.isDateTime().startsWith('2018-03-02T12:00:00')",
            "doneAfter":"@string@.isDateTime().startsWith('2018-03-02T11:30:00')",
            "doneBefore":"@string@.isDateTime().startsWith('2018-03-02T12:00:00')",
            "comments":"#bob",
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":true,
            "assignedTo":"bob",
            "previous":null,
            "group":null,
            "tags":[],
            "doorstep":@*@,
            "ref":null,
            "recurrenceRule":null,
            "metadata":[],
            "weight":null,
            "hasIncidents": false,
            "incidents": [],
            "orgName":"",
            "images":[],
            "next":null,
            "packages":[],
            "createdAt":"@string@.isDateTime()"
          },
          {
            "@id":"@string@.startsWith('/api/tasks')",
            "@context": "/api/contexts/Task",
            "@type":"Task",
            "id":1,
            "type":"DROPOFF",
            "status":"TODO",
            "address":{"@*@":"@*@"},
            "after":"@string@.isDateTime().startsWith('2018-03-02T12:00:00')",
            "before":"@string@.isDateTime().startsWith('2018-03-02T12:30:00')",
            "doneAfter":"@string@.isDateTime().startsWith('2018-03-02T12:00:00')",
            "doneBefore":"@string@.isDateTime().startsWith('2018-03-02T12:30:00')",
            "comments":"#bob",
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":true,
            "assignedTo":"bob",
            "previous":null,
            "group":null,
            "tags":[],
            "doorstep":@*@,
            "ref":null,
            "recurrenceRule":null,
            "metadata":[],
            "weight":null,
            "hasIncidents": false,
            "incidents": [],
            "orgName":"",
            "images":[],
            "next":null,
            "packages":[],
            "createdAt":"@string@.isDateTime()"
          },
          {
            "@id":"@string@.startsWith('/api/tasks')",
            "@context": "/api/contexts/Task",
            "@type":"Task",
            "id":2,
            "type":"DROPOFF",
            "status":"TODO",
            "address":{"@*@":"@*@"},
            "after":"@string@.isDateTime().startsWith('2018-03-02T12:00:00')",
            "before":"@string@.isDateTime().startsWith('2018-03-02T12:30:00')",
            "doneAfter":"@string@.isDateTime().startsWith('2018-03-02T12:00:00')",
            "doneBefore":"@string@.isDateTime().startsWith('2018-03-02T12:30:00')",
            "comments":"#bob",
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":true,
            "assignedTo":"bob",
            "previous":null,
            "group":{"@*@":"@*@"},
            "tags":[],
            "doorstep":@*@,
            "ref":null,
            "recurrenceRule":null,
            "metadata":[],
            "weight":null,
            "hasIncidents": false,
            "incidents": [],
            "orgName":"",
            "images":[],
            "next":null,
            "packages":[],
            "createdAt":"@string@.isDateTime()"
            }
        ],
        "hydra:totalItems": 3,
        "items":[
          {
            "@id":"@string@.startsWith('/api/tasks')",
            "@context": "/api/contexts/Task",
            "@type":"Task",
            "id":4,
            "type":"DROPOFF",
            "status":"TODO",
            "address":{"@*@":"@*@"},
            "after":"@string@.isDateTime().startsWith('2018-03-02T11:30:00')",
            "before":"@string@.isDateTime().startsWith('2018-03-02T12:00:00')",
            "doneAfter":"@string@.isDateTime().startsWith('2018-03-02T11:30:00')",
            "doneBefore":"@string@.isDateTime().startsWith('2018-03-02T12:00:00')",
            "comments":"#bob",
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":true,
            "assignedTo":"bob",
            "previous":null,
            "group":null,
            "tags":[],
            "doorstep":@*@,
            "ref":null,
            "recurrenceRule":null,
            "metadata":[],
            "weight":null,
            "hasIncidents": false,
            "incidents": [],
            "orgName":"",
            "images":[],
            "next":null,
            "packages":[],
            "createdAt":"@string@.isDateTime()"
          },
          {
            "@id":"@string@.startsWith('/api/tasks')",
            "@context": "/api/contexts/Task",
            "@type":"Task",
            "id":1,
            "type":"DROPOFF",
            "status":"TODO",
            "address":{"@*@":"@*@"},
            "after":"@string@.isDateTime().startsWith('2018-03-02T12:00:00')",
            "before":"@string@.isDateTime().startsWith('2018-03-02T12:30:00')",
            "doneAfter":"@string@.isDateTime().startsWith('2018-03-02T12:00:00')",
            "doneBefore":"@string@.isDateTime().startsWith('2018-03-02T12:30:00')",
            "comments":"#bob",
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":true,
            "assignedTo":"bob",
            "previous":null,
            "group":null,
            "tags":[],
            "doorstep":@*@,
            "ref":null,
            "recurrenceRule":null,
            "metadata":[],
            "weight":null,
            "hasIncidents": false,
            "incidents": [],
            "orgName":"",
            "images":[],
            "next":null,
            "packages":[],
            "createdAt":"@string@.isDateTime()"
          },
          {
            "@id":"@string@.startsWith('/api/tasks')",
            "@context": "/api/contexts/Task",
            "@type":"Task",
            "id":2,
            "type":"DROPOFF",
            "status":"TODO",
            "address":{"@*@":"@*@"},
            "after":"@string@.isDateTime().startsWith('2018-03-02T12:00:00')",
            "before":"@string@.isDateTime().startsWith('2018-03-02T12:30:00')",
            "doneAfter":"@string@.isDateTime().startsWith('2018-03-02T12:00:00')",
            "doneBefore":"@string@.isDateTime().startsWith('2018-03-02T12:30:00')",
            "comments":"#bob",
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":true,
            "assignedTo":"bob",
            "previous":null,
            "group":{"@*@":"@*@"},
            "tags":[],
            "doorstep":@*@,
            "ref":null,
            "recurrenceRule":null,
            "metadata":[],
            "weight":null,
            "hasIncidents": false,
            "incidents": [],
            "orgName":"",
            "images":[],
            "next":null,
            "packages":[],
            "createdAt":"@string@.isDateTime()"
          }
        ]
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
      "@type":"TaskList",
      "items":["/api/tasks/4", "/api/tours/1"],
      "distance":@integer@,
      "duration":@integer@,
      "polyline":"@string@",
      "createdAt":"@string@.isDateTime()",
      "updatedAt":"@string@.isDateTime()",
      "date":"2018-03-02",
      "username":"bob"
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
      "@type":"TaskList",
      "items":["/api/tours/1"],
      "distance":@integer@,
      "duration":@integer@,
      "polyline":"@string@",
      "createdAt":"@string@.isDateTime()",
      "updatedAt":"@string@.isDateTime()",
      "date":"2018-03-02",
      "username":"bob"
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
      "@type":"TaskList",
      "items":["/api/tasks/4", "/api/tasks/5", "/api/tasks/6", "/api/tasks/7"],
      "distance":@integer@,
      "duration":@integer@,
      "polyline":"@string@",
      "createdAt":"@string@.isDateTime()",
      "updatedAt":"@string@.isDateTime()",
      "date":"2018-03-02",
      "username":"bob"
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
      "@type":"TaskList",
      "items":["/api/tasks/7", "/api/tasks/4", "/api/tasks/6", "/api/tasks/5"],
      "distance":@integer@,
      "duration":@integer@,
      "polyline":"@string@",
      "createdAt":"@string@.isDateTime()",
      "updatedAt":"@string@.isDateTime()",
      "date":"2018-03-02",
      "username":"bob"
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
      "@id":"/api/task_lists/1",
      "@type":"TaskList",
      "items":["/api/tasks/4"],
      "distance":@integer@,
      "duration":@integer@,
      "polyline":"@string@",
      "createdAt":"@string@.isDateTime()",
      "updatedAt":"@string@.isDateTime()",
      "date":"2018-03-02",
      "username":"bob"
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
      "@type":"TaskList",
      "items":["/api/tasks/4","/api/tours/1"],
      "distance":@integer@,
      "duration":@integer@,
      "polyline":"@string@",
      "createdAt":"@string@.isDateTime()",
      "updatedAt":"@string@.isDateTime()",
      "date":"2018-03-02",
      "username":"bob"
    }
    """