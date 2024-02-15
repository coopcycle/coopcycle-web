Feature: Tasks

  Scenario: Retrieve assigned tasks
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/me/tasks/2018-03-02"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/TaskList",
        "@id":"/api/task_lists/2",
        "@type":"TaskList",
        "hydra:member":[
          {
            "@id":"@string@.startsWith('/api/tasks')",
            "@type":"Task",
            "id":@integer@,
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
            "group":{"@*@":"@*@"},
            "tags":@array@,
            "doorstep":@boolean@,
            "ref":null,
            "recurrenceRule":null,
            "metadata":[],
            "weight":null,
            "hasIncidents": false,
            "orgName":"",
            "images":[],
            "next":null,
            "packages":[],
            "position":0,
            "createdAt":"@string@.isDateTime()",
            "tour":{
              "@id":"/api/tours/1",
              "name":"Example tour",
              "position":@integer@
            }
          },
          {
            "@id":"@string@.startsWith('/api/tasks')",
            "@type":"Task",
            "id":@integer@,
            "type":"DROPOFF",
            "status":"DONE",
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
            "tags":@array@,
            "doorstep":@boolean@,
            "ref":null,
            "recurrenceRule":null,
            "metadata":[],
            "weight":null,
            "hasIncidents": false,
            "orgName":"",
            "images":[],
            "next":null,
            "packages":[],
            "position":1,
            "createdAt":"@string@.isDateTime()",
            "tour":null
          }
        ],
        "hydra:totalItems":2,
        "items":[
          {
            "@id":"@string@.startsWith('/api/tasks')",
            "@type":"Task",
            "id":@integer@,
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
            "group":{"@*@":"@*@"},
            "tags":@array@,
            "doorstep":@boolean@,
            "ref":null,
            "recurrenceRule":null,
            "metadata":[],
            "weight":null,
            "hasIncidents": false,
            "orgName":"",
            "images":[],
            "next":null,
            "packages":[],
            "position":0,
            "createdAt":"@string@.isDateTime()",
            "tour":{
              "@id":"/api/tours/1",
              "name":"Example tour",
              "position":@integer@
            }
          },
          {
            "@id":"@string@.startsWith('/api/tasks')",
            "@type":"Task",
            "id":@integer@,
            "type":"DROPOFF",
            "status":"DONE",
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
            "tags":@array@,
            "doorstep":@boolean@,
            "ref":null,
            "recurrenceRule":null,
            "metadata":[],
            "weight":null,
            "hasIncidents": false,
            "orgName":"",
            "images":[],
            "next":null,
            "packages":[],
            "position":1,
            "createdAt":"@string@.isDateTime()",
            "tour":null
          }
        ],
        "distance":@integer@,
        "duration":@integer@,
        "polyline":@string@,
        "date":"2018-03-02",
        "username":"bob",
        "createdAt":"@string@.isDateTime()",
        "updatedAt":"@string@.isDateTime()"
      }
      """

  Scenario: Retrieve assigned tasks when not created yet
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/me/tasks/2020-03-02"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/TaskList",
        "@id":"/api/task_lists/5",
        "@type":"TaskList",
        "hydra:member":[],
        "hydra:totalItems":0,
        "items":[],
        "distance":@integer@,
        "duration":@integer@,
        "polyline":@string@,
        "date":"2020-03-02",
        "username":"bob",
        "createdAt":"@string@.isDateTime()",
        "updatedAt":"@string@.isDateTime()"
      }
      """

  Scenario: Retrieve task events
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/tasks/2/events"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"/api/tasks",
        "@type":"hydra:Collection",
        "hydra:member":@array@,
        "hydra:totalItems":2,
        "hydra:search":{
          "@type":"hydra:IriTemplate",
          "hydra:template":"/api/tasks/2/events{?date,assigned,organization}",
          "hydra:variableRepresentation":"BasicRepresentation",
          "hydra:mapping":@array@
        }
      }
      """

  Scenario: Not authorized to retrieve task events
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the courier "sarah" is loaded:
      | email     | sarah@coopcycle.org |
      | password  | 123456              |
      | telephone | 0033612345678       |
    And the user "sarah" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "GET" request to "/api/tasks/2/events"
    Then the response status code should be 403
    And the response should be in JSON

  Scenario: Mark task as done
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/tasks/2/done" with body:
      """
      {}
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"/api/tasks/2",
        "@type":"Task",
        "id":2,
        "type":"DROPOFF",
        "status":"DONE",
        "address":@...@,
        "after":"2018-03-02T11:30:00+01:00",
        "before":"2018-03-02T12:00:00+01:00",
        "doneAfter":"2018-03-02T11:30:00+01:00",
        "doneBefore":"2018-03-02T12:00:00+01:00",
        "comments":@string@,
        "updatedAt":"@string@.isDateTime()",
        "isAssigned":true,
        "assignedTo":"bob",
        "previous":null,
        "group":{
          "id":@integer@,
          "name":"Group #1",
          "tags":[{
            "name":"Important",
            "slug":"important",
            "color":"#FF0000"
          }]
        },
        "tags":@array@
      }
      """

  Scenario: Reschedule failed or cancelled task
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/tasks/2/reschedule" with body:
      """
      {
	      "after": "2023-09-13T12:00:00+02:00",
	      "before": "2023-09-13T12:45:00+02:00"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"/api/tasks/2",
        "@type":"Task",
        "id":2,
        "type":"DROPOFF",
        "status":"TODO",
        "address":@...@,
        "after":"2023-09-13T12:00:00+02:00",
        "before":"2023-09-13T12:45:00+02:00",
        "doneAfter":"2023-09-13T12:00:00+02:00",
        "doneBefore":"2023-09-13T12:45:00+02:00",
        "comments":@string@,
        "updatedAt":"@string@.isDateTime()",
        "isAssigned":false,
        "assignedTo":null,
        "previous":null,
        "group":{
          "id":@integer@,
          "name":"Group #1",
          "tags":[{
            "name":"Important",
            "slug":"important",
            "color":"#FF0000"
          }]
        },
        "tags":@array@
      }
      """

  Scenario: Add task to a group
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
      | users.yml           |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_groups/1/tasks" with body:
      """
      {
        "tasks": [
          "/api/tasks/1",
          "/api/tasks/3"
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
          "@context": "/api/contexts/TaskGroup",
          "@id": "/api/task_groups/1",
          "@type": "TaskGroup",
          "id": 1,
          "name": "Group #1",
          "tasks":"@array@.count(3)"
      }
      """

  Scenario: Remove task from a group
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
      | users.yml           |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "DELETE" request to "/api/tasks/2/group"
    Then the response status code should be 204
    When the user "bob" is authenticated
    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/task_groups/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/TaskGroup",
        "@id": "/api/task_groups/1",
        "@type": "TaskGroup",
        "id": 1,
        "name": "Group #1",
        "tasks": []
      }
      """

  Scenario: Start a task
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/tasks/2/start" with body:
      """
      {}
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"/api/tasks/2",
        "@type":"Task",
        "id":2,
        "type":"DROPOFF",
        "status":"DOING",
        "address":@...@,
        "after":"2018-03-02T11:30:00+01:00",
        "before":"2018-03-02T12:00:00+01:00",
        "doneAfter":"2018-03-02T11:30:00+01:00",
        "doneBefore":"2018-03-02T12:00:00+01:00",
        "comments":@string@,
        "updatedAt":"@string@.isDateTime()",
        "isAssigned":true,
        "assignedTo":"bob",
        "previous":null,
        "group":{
          "id":@integer@,
          "name":"Group #1",
          "tags":[{
            "name":"Important",
            "slug":"important",
            "color":"#FF0000"
          }]
        },
        "tags":@array@
      }
      """
    # Trying to start twice should do nothing
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/tasks/2/start" with body:
      """
      {}
      """
    Then the response status code should be 200

  Scenario: Mark task as done with contact name
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/tasks/2/done" with body:
      """
      {
        "contactName":"John Doe"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"/api/tasks/2",
        "@type":"Task",
        "id":2,
        "type":"DROPOFF",
        "status":"DONE",
        "address":{
          "@id":"/api/addresses/2",
          "@type":"http://schema.org/Place",
          "contactName":"John Doe",
          "description":null,
          "geo":{
            "@type":"GeoCoordinates",
            "latitude":48.846656,
            "longitude":2.369052
          },
          "streetAddress":"18, avenue Ledru-Rollin 75012 Paris 12ème",
          "telephone":null,
          "firstName":"John",
          "lastName":"Doe",
          "name":null
        },
        "comments":"#bob",
        "updatedAt":"@string@.isDateTime()",
        "group":"@*@",
        "images":[],
        "tags":[],
        "after":"2018-03-02T11:30:00+01:00",
        "before":"2018-03-02T12:00:00+01:00",
        "isAssigned":true,
        "doneAfter":"2018-03-02T11:30:00+01:00",
        "doneBefore":"2018-03-02T12:00:00+01:00",
        "assignedTo":"bob",
        "previous":null,
        "next":null,
        "doorstep":false,
        "orgName": "",
        "ref":null,
        "recurrenceRule": null,
        "metadata": [],
        "weight":null,
        "hasIncidents": false,
        "packages": [],
        "createdAt":"@string@.isDateTime()",
        "tour":{
          "@id":"/api/tours/1",
          "name":"Example tour",
          "position":@integer@
        }
      }
      """

  Scenario: Mark task as failed with notes
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/tasks/2/failed" with body:
      """
      {
        "notes": "Address not found"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"/api/tasks/2",
        "@type":"Task",
        "id":2,
        "type":"DROPOFF",
        "status":"FAILED",
        "address":@...@,
        "after":"2018-03-02T11:30:00+01:00",
        "before":"2018-03-02T12:00:00+01:00",
        "doneAfter":"2018-03-02T11:30:00+01:00",
        "doneBefore":"2018-03-02T12:00:00+01:00",
        "comments":@string@,
        "updatedAt":"@string@.isDateTime()",
        "isAssigned":true,
        "assignedTo":"bob",
        "previous":null,
        "group":null,
        "tags":@array@
      }
      """

  Scenario: Mark task as failed with failure reason via failed endpoint
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When the user "bob" sends a "GET" request to "/api/tasks/2"
    Then the response status code should be 200
    And the response should be in JSON
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/tasks/2/failed" with body:
      """
      {
        "reason": "DAMAGED"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"/api/tasks/2",
        "@type":"Task",
        "id":2,
        "type":"DROPOFF",
        "status":"FAILED",
        "address":@...@,
        "after":"2018-03-02T11:30:00+01:00",
        "before":"2018-03-02T12:00:00+01:00",
        "doneAfter":"2018-03-02T11:30:00+01:00",
        "doneBefore":"2018-03-02T12:00:00+01:00",
        "comments":@string@,
        "updatedAt":"@string@.isDateTime()",
        "isAssigned":true,
        "assignedTo":"bob",
        "previous":null,
        "group":null,
        "hasIncidents": true,
        "tags":@array@
      }
      """
    And the user "bob" sends a "GET" request to "/api/tasks/2/events"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"/api/tasks",
        "@type":"hydra:Collection",
        "hydra:member":[
          "@...@",
          {
            "@id":"@string@.startsWith('/api/task_events')",
            "@type":"TaskEvent",
            "name":"task:failed",
            "data":{"reason":"DAMAGED","notes":"DAMAGED"},
            "createdAt":"@string@.isDateTime()"
          },
          {
            "@id":"@string@.startsWith('/api/task_events')",
            "@type":"TaskEvent",
            "name":"task:incident-reported",
            "data":{"reason":"DAMAGED","notes":"DAMAGED"},
            "createdAt":"@string@.isDateTime()"
          }
        ],
        "hydra:totalItems":4,
        "hydra:search":{
          "@*@":"@*@"
        }
      }
      """

  Scenario: Mark task as failed with failure reason via incidents endpoint
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When the user "bob" sends a "GET" request to "/api/tasks/2"
    Then the response status code should be 200
    And the response should be in JSON
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/tasks/2/incidents" with body:
      """
      {
        "reason": "DAMAGED",
        "notes": "PACKAGE WET"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"/api/tasks/2",
        "@type":"Task",
        "id":2,
        "type":"DROPOFF",
        "status":"TODO",
        "address":@...@,
        "after":"2018-03-02T11:30:00+01:00",
        "before":"2018-03-02T12:00:00+01:00",
        "doneAfter":"2018-03-02T11:30:00+01:00",
        "doneBefore":"2018-03-02T12:00:00+01:00",
        "comments":@string@,
        "updatedAt":"@string@.isDateTime()",
        "isAssigned":true,
        "assignedTo":"bob",
        "previous":null,
        "group":null,
        "hasIncidents": true,
        "tags":@array@
      }
      """
    And the user "bob" sends a "GET" request to "/api/tasks/2/events"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"/api/tasks",
        "@type":"hydra:Collection",
        "hydra:member":[
          "@...@",
          {
            "@id":"@string@.startsWith('/api/task_events')",
            "@type":"TaskEvent",
            "name":"task:incident-reported",
            "data":{"reason":"DAMAGED","notes":"PACKAGE WET"},
            "createdAt":"@string@.isDateTime()"
          }
        ],
        "hydra:totalItems":3,
        "hydra:search":{
          "@*@":"@*@"
        }
      }
      """

  Scenario: Task is already completed
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/tasks/7/done" with body:
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
        "hydra:description":"Task #7 is already completed",
        "trace":@array@
      }
      """

  Scenario: Previous task must be completed before marking as done
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/tasks/5/done" with body:
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
        "hydra:description":@string@,
        "trace":@array@
      }
      """

  Scenario: Previous task must be completed before marking as failed
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/tasks/5/failed" with body:
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
        "hydra:description":@string@,
        "trace":@array@
      }
      """

  Scenario: Only assigned courier can mark a task as done
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the courier "steve" is loaded:
      | email     | steve@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "steve" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "steve" sends a "PUT" request to "/api/tasks/2/done" with body:
      """
      {}
      """
    Then the response status code should be 403
    And the response should be in JSON

  Scenario: Only assigned courier can mark a task as failed
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the courier "steve" is loaded:
      | email     | steve@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "steve" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "steve" sends a "PUT" request to "/api/tasks/2/failed" with body:
      """
      {}
      """
    Then the response status code should be 403
    And the response should be in JSON

  Scenario: Cancelled task can't be marked as done
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/tasks/6/done" with body:
      """
      {}
      """
    Then the response status code should be 400
    And the response should be in JSON

  Scenario: Cancelled task can't be marked as failed
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/tasks/6/failed" with body:
      """
      {}
      """
    Then the response status code should be 400
    And the response should be in JSON

  Scenario: Create task
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | dispatch.yml        |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/tasks" with body:
      """
      {
        "type": "DROPOFF",
        "address": {
          "streetAddress": "101 Rue de la Paix, 75002 Paris",
          "postalCode": "75002",
          "addressLocality": "Paris",
          "description": "Sonner à l'interphone",
          "telephone": "+33612345678",
          "geo": {
            "latitude": 48.870473,
            "longitude": 2.331933
          }
        },
        "doneAfter": "2018-12-24T23:30:00+01:00",
        "doneBefore": "2018-12-24T23:59:59+01:00",
        "weight": 800
      }
      """
    Then the response status code should be 201
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"@string@.startsWith('/api/tasks')",
        "@type":"Task",
        "id":@integer@,
        "type":"DROPOFF",
        "status":"TODO",
        "address":{
          "@id":"@string@.startsWith('/api/addresses')",
          "@type":"http://schema.org/Place",
          "firstName":null,
          "lastName":null,
          "description": "Sonner à l'interphone",
          "geo":{
            "@type":"GeoCoordinates",
            "latitude":48.870473,
            "longitude":2.331933
          },
          "streetAddress":"101 Rue de la Paix, 75002 Paris",
          "telephone":"+33612345678",
          "name":null,
          "contactName": null
        },
        "after":"2018-12-24T23:30:00+01:00",
        "before":"2018-12-24T23:59:59+01:00",
        "doneAfter":"2018-12-24T23:30:00+01:00",
        "doneBefore":"2018-12-24T23:59:59+01:00",
        "comments":"",
        "updatedAt":"@string@.isDateTime()",
        "isAssigned":false,
        "assignedTo":null,
        "previous":null,
        "next":null,
        "group":null,
        "tags":@array@,
        "images":@array@,
        "doorstep":false,
        "orgName": "",
        "ref":null,
        "recurrenceRule": null,
        "metadata": [],
        "weight": 800,
        "hasIncidents": false,
        "packages": [],
        "createdAt":"@string@.isDateTime()",
        "tour":null
      }
      """

  Scenario: Create task with after & before
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | dispatch.yml        |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/tasks" with body:
      """
      {
        "type": "DROPOFF",
        "address": {
          "streetAddress": "101 Rue de la Paix, 75002 Paris",
          "postalCode": "75002",
          "addressLocality": "Paris",
          "description": "Sonner à l'interphone",
          "geo": {
            "latitude": 48.870473,
            "longitude": 2.331933
          }
        },
        "comments": "Hello, world",
        "after": "2018-12-24T23:30:00+01:00",
        "before": "2018-12-24T23:59:59+01:00",
        "tags": ["important"]
      }
      """
    Then the response status code should be 201
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"@string@.startsWith('/api/tasks')",
        "@type":"Task",
        "id":@integer@,
        "type":"DROPOFF",
        "status":"TODO",
        "address":{
          "@id":"@string@.startsWith('/api/addresses')",
          "@type":"http://schema.org/Place",
          "firstName":null,
          "lastName":null,
          "description": "Sonner à l'interphone",
          "geo":{
            "@type":"GeoCoordinates",
            "latitude":48.870473,
            "longitude":2.331933
          },
          "streetAddress":"101 Rue de la Paix, 75002 Paris",
          "telephone":null,
          "name":null,
          "contactName": null
        },
        "after":"2018-12-24T23:30:00+01:00",
        "before":"2018-12-24T23:59:59+01:00",
        "doneAfter":"2018-12-24T23:30:00+01:00",
        "doneBefore":"2018-12-24T23:59:59+01:00",
        "comments":"Hello, world",
        "updatedAt":"@string@.isDateTime()",
        "isAssigned":false,
        "assignedTo":null,
        "previous":null,
        "next":null,
        "group":null,
        "tags": [
          {"name":"Important","slug":"important","color":"#000000"}
        ],
        "images":@array@,
        "doorstep":false,
        "orgName": "",
        "ref":null,
        "recurrenceRule": null,
        "metadata": [],
        "weight":null,
        "hasIncidents": false,
        "packages": [],
        "createdAt":"@string@.isDateTime()",
        "tour":null
      }
      """

  Scenario: Not authorized to create task
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | dispatch.yml        |
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "POST" request to "/api/tasks" with body:
      """
      {
        "type": "DROPOFF",
        "address": {
          "streetAddress": "101 Rue de la Paix, 75002 Paris",
          "geo": {
            "latitude": 48.870473,
            "longitude": 2.331933
          }
        },
        "doneAfter": "2018-12-24T23:30:00+01:00",
        "doneBefore": "2018-12-24T23:59:59+01:00"
      }
      """
    Then the response status code should be 401

  Scenario: Not authorized to retrieve task
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | deliveries.yml      |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_STORE"
    And the store with name "Acme2" belongs to user "bob"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    When the user "bob" sends a "GET" request to "/api/tasks/1"
    Then the response status code should be 403
    And the response should be in JSON

  Scenario: Authorized to retrieve task
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | deliveries.yml      |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_STORE"
    And the store with name "Acme" belongs to user "bob"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    When the user "bob" sends a "GET" request to "/api/tasks/1"
    Then the response status code should be 200
    And the response should be in JSON

  Scenario: Not enough permissions to create task
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | dispatch.yml        |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "POST" request to "/api/tasks" with body:
      """
      {
        "type": "DROPOFF",
        "address": {
          "streetAddress": "101 Rue de la Paix, 75002 Paris",
          "geo": {
            "latitude": 48.870473,
            "longitude": 2.331933
          }
        },
        "doneAfter": "2018-12-24T23:30:00+01:00",
        "doneBefore": "2018-12-24T23:59:59+01:00"
      }
      """
    Then the response status code should be 401

  Scenario: Retrieve tasks filtered by date
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | dispatch.yml        |
    And the user "sarah" has role "ROLE_COURIER"
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "GET" request to "/api/tasks?date=2018-12-01"
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"/api/tasks",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/tasks/1",
            "@type":"Task",
            "id":@integer@,
            "type":"DROPOFF",
            "status":"TODO",
            "address":{"@*@":"@*@"},
            "after":"2018-12-01T10:30:00+01:00",
            "before":"2018-12-01T11:00:00+01:00",
            "doneAfter":"2018-12-01T10:30:00+01:00",
            "doneBefore":"2018-12-01T11:00:00+01:00",
            "comments":"",
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":true,
            "assignedTo":"sarah",
            "previous":null,
            "next":null,
            "group":null,
            "tags":[],
            "doorstep":false,
            "orgName":"",
            "images":[],
            "ref": null,
            "recurrenceRule":null,
            "metadata":{
              "foo":"bar",
              "baz":"bat"
            },
            "weight":null,
            "hasIncidents": false,
            "packages": [],
            "createdAt":"@string@.isDateTime()",
            "tour":null
          },
          {
            "@id":"/api/tasks/2",
            "@type":"Task",
            "id":@integer@,
            "type":"DROPOFF",
            "status":"TODO",
            "address":{"@*@":"@*@"},
            "after":"2018-11-30T11:30:00+01:00",
            "before":"2018-12-02T12:00:00+01:00",
            "doneAfter":"2018-11-30T11:30:00+01:00",
            "doneBefore":"2018-12-02T12:00:00+01:00",
            "comments":"",
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":true,
            "assignedTo":"sarah",
            "previous":null,
            "next":null,
            "group":null,
            "tags":[],
            "doorstep":false,
            "orgName":"",
            "images":[],
            "ref": null,
            "recurrenceRule":null,
            "metadata":[],
            "weight":null,
            "hasIncidents": false,
            "packages": [],
            "createdAt":"@string@.isDateTime()",
            "tour":null
          }
        ],
        "hydra:totalItems":2,
        "hydra:view":{
          "@id":"/api/tasks?date=2018-12-01",
          "@type":"hydra:PartialCollectionView"
        },
        "hydra:search":{
          "@type":"hydra:IriTemplate",
          "hydra:template":"/api/tasks{?date,assigned,organization}",
          "hydra:variableRepresentation":"BasicRepresentation",
          "hydra:mapping":@array@
        }
      }
      """

  Scenario: Retrieve tasks filtered by date for admin
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | dispatch.yml        |
    And the user "sarah" has role "ROLE_ADMIN"
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "GET" request to "/api/tasks?date=2018-12-01"
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"/api/tasks",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/tasks/1",
            "@type":"Task",
            "id":1,
            "type":"DROPOFF",
            "status":"TODO",
            "address":@...@,
            "after":"2018-12-01T10:30:00+01:00",
            "before":"2018-12-01T11:00:00+01:00",
            "doneAfter":"2018-12-01T10:30:00+01:00",
            "doneBefore":"2018-12-01T11:00:00+01:00",
            "comments":null,
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":true,
            "assignedTo":"sarah",
            "previous":null,
            "next":null,
            "group":null,
            "tags":@array@
          },
          {
            "@id":"/api/tasks/2",
            "@type":"Task",
            "id":2,
            "type":"DROPOFF",
            "status":"TODO",
            "address":@...@,
            "after":"2018-12-01T11:30:00+01:00",
            "before":"2018-12-01T12:00:00+01:00",
            "doneAfter":"2018-12-01T11:30:00+01:00",
            "doneBefore":"2018-12-01T12:00:00+01:00",
            "comments":null,
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":true,
            "assignedTo":"sarah",
            "previous":null,
            "next":null,
            "group":null,
            "tags":@array@
          },
          {
            "@id":"/api/tasks/6",
            "@type":"Task",
            "id":6,
            "type":"DROPOFF",
            "status":"TODO",
            "address":@...@,
            "doneAfter":"2018-12-01T12:00:00+01:00",
            "doneBefore":"2018-12-01T12:30:00+01:00",
            "after":"2018-12-01T12:00:00+01:00",
            "before":"2018-12-01T12:30:00+01:00",
            "comments":null,
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":false,
            "assignedTo":null,
            "previous":null,
            "next":null,
            "group":null,
            "tags":@array@
          },
          {
            "@id":"/api/tasks/7",
            "@type":"Task",
            "id":7,
            "type":"DROPOFF",
            "status":"TODO",
            "address":@...@,
            "doneAfter":"2018-12-01T12:00:00+01:00",
            "doneBefore":"2018-12-01T12:30:00+01:00",
            "comments":"",
            "updatedAt":"2019-11-14T18:48:59+01:00",
            "group":null,
            "images":@array@,
            "tags":@array@,
            "isAssigned":true,
            "after":"2018-12-01T12:00:00+01:00",
            "before":"2018-12-01T12:30:00+01:00",
            "assignedTo":"bob",
            "previous":null,
            "next":null
          }
        ],
        "hydra:totalItems":4,
        "hydra:view":{
          "@id":"/api/tasks?date=2018-12-01",
          "@type":"hydra:PartialCollectionView"
        },
        "hydra:search":{
          "@type":"hydra:IriTemplate",
          "hydra:template":"/api/tasks{?date,assigned,organization}",
          "hydra:variableRepresentation":"BasicRepresentation",
          "hydra:mapping":@array@
        }
      }
      """

  Scenario: Retrieve unassigned tasks filtered by date for admin
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | dispatch.yml        |
    And the user "sarah" has role "ROLE_ADMIN"
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "GET" request to "/api/tasks?date=2018-12-01&assigned=no"
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"/api/tasks",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/tasks/6",
            "@type":"Task",
            "id":6,
            "type":"DROPOFF",
            "status":"TODO",
            "address":@...@,
            "after":"2018-12-01T12:00:00+01:00",
            "before":"2018-12-01T12:30:00+01:00",
            "doneAfter":"2018-12-01T12:00:00+01:00",
            "doneBefore":"2018-12-01T12:30:00+01:00",
            "comments":null,
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":false,
            "assignedTo":null,
            "previous":null,
            "next":null,
            "group":null,
            "tags":@array@
          }
        ],
        "hydra:totalItems":1,
        "hydra:view":{
          "@id":"/api/tasks?date=2018-12-01\u0026assigned=no",
          "@type":"hydra:PartialCollectionView"
        },
        "hydra:search":{
          "@type":"hydra:IriTemplate",
          "hydra:template":"/api/tasks{?date,assigned,organization}",
          "hydra:variableRepresentation":"BasicRepresentation",
          "hydra:mapping":@array@
        }
      }
      """

  Scenario: Duplicate task
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | dispatch.yml        |
    And the user "sarah" has role "ROLE_ADMIN"
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/tasks/1/duplicate" with body:
      """
      {}
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"@string@.startsWith('/api/tasks')",
        "@type":"Task",
        "id":@integer@,
        "type":"DROPOFF",
        "status":"TODO",
        "address":@...@,
        "after":"2018-12-01T10:30:00+01:00",
        "before":"2018-12-01T11:00:00+01:00",
        "doneAfter":"2018-12-01T10:30:00+01:00",
        "doneBefore":"2018-12-01T11:00:00+01:00",
        "comments":"",
        "updatedAt":"@string@.isDateTime()",
        "isAssigned":false,
        "assignedTo":null,
        "previous":null,
        "next":null,
        "group":null,
        "tags":@array@
      }
      """

  Scenario: Cannot edit task type
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | dispatch.yml        |
    And the user "sarah" has role "ROLE_ADMIN"
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/tasks/5" with body:
      """
      {
        "type": "PICKUP"
      }
      """
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ConstraintViolationList",
        "@type":"ConstraintViolationList",
        "hydra:title":"An error occurred",
        "hydra:description":@string@,
        "violations":[
          {
            "propertyPath":"type",
            "message":@string@,
            "code":null
          }
        ]
      }
      """

  Scenario: Can edit task type
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | dispatch.yml        |
    And the user "sarah" has role "ROLE_ADMIN"
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/tasks/1" with body:
      """
      {
        "type": "PICKUP"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON

  Scenario: Can't edit task status
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | deliveries.yml      |
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "PUT" request to "/api/deliveries/1" with body:
      """
      {
        "pickup": {
          "status": "DONE"
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"/api/deliveries/1",
        "@type":"http://schema.org/ParcelDelivery",
        "id":1,
        "pickup":{
          "@id":"/api/tasks/1",
          "@type":"Task",
          "id":1,
          "status":"TODO",
          "address":@...@,
          "doneAfter":"2019-11-12T18:00:00+01:00",
          "doneBefore":"2019-11-12T18:30:00+01:00",
          "comments":"",
          "after":"2019-11-12T18:00:00+01:00",
          "before":"2019-11-12T18:30:00+01:00"
        },
        "dropoff":{
          "@id":"/api/tasks/2",
          "@type":"Task",
          "id":2,
          "status":"TODO",
          "address":@...@,
          "doneAfter":"2019-11-12T19:00:00+01:00",
          "doneBefore":"2019-11-12T19:30:00+01:00",
          "comments":"",
          "after":"2019-11-12T19:00:00+01:00",
          "before":"2019-11-12T19:30:00+01:00"
        },
        "trackingUrl": @string@
      }
      """

  Scenario: Can complete pickup & dropoff
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | deliveries.yml      |
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "PUT" request to "/api/deliveries/1/pick" with body:
      """
      {
        "comments": "no problem"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"/api/deliveries/1",
        "@type":"http://schema.org/ParcelDelivery",
        "id":1,
        "pickup":{
          "@id":"/api/tasks/1",
          "@type":"Task",
          "id":1,
          "status":"DONE",
          "address":@...@,
          "doneAfter":"2019-11-12T18:00:00+01:00",
          "doneBefore":"2019-11-12T18:30:00+01:00",
          "comments":"",
          "after":"2019-11-12T18:00:00+01:00",
          "before":"2019-11-12T18:30:00+01:00"
        },
        "dropoff":{
          "@id":"/api/tasks/2",
          "@type":"Task",
          "id":2,
          "status":"TODO",
          "address":@...@,
          "doneAfter":"2019-11-12T19:00:00+01:00",
          "doneBefore":"2019-11-12T19:30:00+01:00",
          "comments":"",
          "after":"2019-11-12T19:00:00+01:00",
          "before":"2019-11-12T19:30:00+01:00"
        },
        "trackingUrl": @string@
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "PUT" request to "/api/deliveries/1/drop" with body:
      """
      {
        "comments": "no problem"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"/api/deliveries/1",
        "@type":"http://schema.org/ParcelDelivery",
        "id":1,
        "pickup":{
          "@id":"/api/tasks/1",
          "@type":"Task",
          "id":1,
          "status":"DONE",
          "address":@...@,
          "doneAfter":"2019-11-12T18:00:00+01:00",
          "doneBefore":"2019-11-12T18:30:00+01:00",
          "comments":"",
          "after":"2019-11-12T18:00:00+01:00",
          "before":"2019-11-12T18:30:00+01:00"
        },
        "dropoff":{
          "@id":"/api/tasks/2",
          "@type":"Task",
          "id":2,
          "status":"DONE",
          "address":@...@,
          "doneAfter":"2019-11-12T19:00:00+01:00",
          "doneBefore":"2019-11-12T19:30:00+01:00",
          "comments":"",
          "after":"2019-11-12T19:00:00+01:00",
          "before":"2019-11-12T19:30:00+01:00"
        },
        "trackingUrl": @string@
      }
      """

  Scenario: Import tasks with CSV format
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
      | tags.yml            |
    Given the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "text/csv"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/tasks/import" with body:
      """
      type,address.streetAddress,address.telephone,address.name,after,before,tags
      pickup,"1, rue de Rivoli Paris",,Foo,2018-02-15 09:00,2018-02-15 10:00,"important"
      dropoff,"54, rue du Faubourg Saint Denis Paris",,Bar,2018-02-15 09:00,2018-02-15 10:00,"important fragile"
      dropoff,"68, rue du Faubourg Saint Denis Paris",,Baz,2018-02-15 10:00,2018-02-15 11:00,"fragile"
      dropoff,"42, rue de Rivoli Paris",,Bat,2018-02-15 11:30,2018-02-15 12:00,
      """
    Then the response status code should be 201
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/TaskGroup",
        "@id":"/api/task_groups/1",
        "@type":"TaskGroup",
        "id": 1,
        "name":@string@,
        "tasks":[
          "@string@.matchRegex('#/api/tasks/[0-9]+#')",
          "@string@.matchRegex('#/api/tasks/[0-9]+#')",
          "@string@.matchRegex('#/api/tasks/[0-9]+#')",
          "@string@.matchRegex('#/api/tasks/[0-9]+#')"
        ]
      }
      """
    And all the tasks should belong to organization with name "Acme"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "GET" request to "/api/tasks/1"
    Then the response status code should be 200

  Scenario: Import tasks with CSV format (one line)
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
      | tags.yml            |
    Given the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "text/csv"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/tasks/import" with body:
      """
      type,address.streetAddress,address.telephone,address.name,after,before,tags
      pickup,"1, rue de Rivoli Paris",,Foo,2018-02-15 09:00,2018-02-15 10:00,"important"
      """
    Then the response status code should be 201
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/TaskGroup",
        "@id":"/api/task_groups/1",
        "@type":"TaskGroup",
        "id": 1,
        "name":@string@,
        "tasks":[
          "@string@.matchRegex('#/api/tasks/[0-9]+#')"
        ]
      }
      """
    And all the tasks should belong to organization with name "Acme"

  Scenario: Import tasks with CSV format with duplicate ref
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    Given the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "text/csv"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/tasks/import" with body:
      """
      type,address.streetAddress,address.telephone,address.name,after,before,ref
      pickup,"1, rue de Rivoli Paris",,Foo,2018-02-15 09:00,2018-02-15 10:00,123456
      dropoff,"54, rue du Faubourg Saint Denis Paris",,Bar,2018-02-15 09:00,2018-02-15 10:00,123456
      """
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ConstraintViolationList",
        "@type":"ConstraintViolationList",
        "hydra:title":"An error occurred",
        "hydra:description":@string@,
        "violations":[
          {
            "propertyPath":"tasks[1]",
            "message":@string@,
            "code":null
          }
        ]
      }
      """

  Scenario: Import tasks with CSV format with existing ref
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    Given the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    And a task with ref "123456" exists and is attached to store with name "Acme"
    When I add "Content-Type" header equal to "text/csv"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/tasks/import" with body:
      """
      type,address.streetAddress,address.telephone,address.name,after,before,ref
      pickup,"1, rue de Rivoli Paris",,Foo,2018-02-15 09:00,2018-02-15 10:00,654321
      dropoff,"54, rue du Faubourg Saint Denis Paris",,Bar,2018-02-15 09:00,2018-02-15 10:00,123456
      """
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ConstraintViolationList",
        "@type":"ConstraintViolationList",
        "hydra:title":"An error occurred",
        "hydra:description":@string@,
        "violations":[
          {
            "propertyPath":"tasks[1].ref",
            "message":@string@,
            "code":@string@
          }
        ]
      }
      """

  Scenario: Authorized to retrieve task group
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    Given the store with name "Acme" has imported tasks:
      | type    | address.streetAddress                 | after            | before           |
      | pickup  | 1, rue de Rivoli Paris                | 2018-02-15 09:00 | 2018-02-15 10:00 |
      | dropoff | 54, rue du Faubourg Saint Denis Paris | 2018-02-15 09:00 | 2018-02-15 10:00 |
    Given the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "GET" request to "/api/task_groups/1"
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/TaskGroup",
        "@id":"/api/task_groups/1",
        "@type":"TaskGroup",
        "id": 1,
        "name":@string@,
        "tasks":[
          "@string@.matchRegex('#/api/tasks/[0-9]+#')",
          "@string@.matchRegex('#/api/tasks/[0-9]+#')"
        ]
      }
      """

  Scenario: Not authorized to retrieve task group
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    Given the store with name "Acme" has imported tasks:
      | type    | address.streetAddress                 | after            | before           |
      | pickup  | 1, rue de Rivoli Paris                | 2018-02-15 09:00 | 2018-02-15 10:00 |
      | dropoff | 54, rue du Faubourg Saint Denis Paris | 2018-02-15 09:00 | 2018-02-15 10:00 |
    Given the store with name "Acme2" has an OAuth client named "Acme2"
    And the OAuth client with name "Acme2" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme2" sends a "GET" request to "/api/task_groups/1"
    Then the response status code should be 403

  Scenario: Create task with invalid address
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | dispatch.yml        |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/tasks" with body:
      """
      {
        "address": {},
        "doneAfter": "2020-09-01T13:53:29.536Z",
        "doneBefore": "2020-09-01T14:23:29.537Z"
      }
      """
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
      """
      {
         "@context":"/api/contexts/ConstraintViolationList",
         "@type":"ConstraintViolationList",
         "hydra:title":"An error occurred",
         "hydra:description":@string@,
         "violations":[
            {
              "propertyPath":"address.geo",
              "message":@string@,
              "code":@string@
            },
            {
              "propertyPath":"address.streetAddress",
              "message":@string@,
              "code":@string@
            }
         ]
      }
      """

  Scenario: Create and update task group
    Given the fixtures files are loaded:
      | dispatch.yml        |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_groups" with body:
      """
      {
        "name": "Fancy group",
        "tasks": [
          "/api/tasks/1",
          "/api/tasks/2"
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/TaskGroup",
        "@id":"/api/task_groups/1",
        "@type":"TaskGroup",
        "id": 1,
        "name":"Fancy group",
        "tasks":"@array@.count(2)"
      }
      """
    When the user "bob" is authenticated
    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/task_groups/1" with body:
      """
      {
        "name": "New name group",
        "tasks": [
          "/api/tasks/1",
          "/api/tasks/2"
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/TaskGroup",
        "@id":"/api/task_groups/1",
        "@type":"TaskGroup",
        "id": 1,
        "name":"New name group",
        "tasks":"@array@.count(2)"
      }
      """

  Scenario: Create and update task group
    Given the fixtures files are loaded:
      | dispatch.yml        |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/task_groups" with body:
      """
      {
        "name": "Fancy group",
        "tasks": [
          "/api/tasks/1",
          "/api/tasks/2"
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/TaskGroup",
        "@id":"/api/task_groups/1",
        "@type":"TaskGroup",
        "id": 1,
        "name":"Fancy group",
        "tasks":"@array@.count(2)"
      }
      """
    When the user "bob" is authenticated
    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/task_groups/1" with body:
      """
      {
        "name": "New name group",
        "tasks": [
          "/api/tasks/1",
          "/api/tasks/2"
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/TaskGroup",
        "@id":"/api/task_groups/1",
        "@type":"TaskGroup",
        "id": 1,
        "name":"New name group",
        "tasks":"@array@.count(2)"
      }
      """

  Scenario: Authorized to retrieve task events
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    Given the store with name "Acme" has imported tasks:
      | type    | address.streetAddress                 | after            | before           |
      | pickup  | 1, rue de Rivoli Paris                | 2018-02-15 09:00 | 2018-02-15 10:00 |
      | dropoff | 54, rue du Faubourg Saint Denis Paris | 2018-02-15 09:00 | 2018-02-15 10:00 |
    Given the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "GET" request to "/api/tasks/1/events"
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"/api/tasks",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/task_events/1",
            "@type":"TaskEvent",
            "name":"task:created",
            "data":[],
            "createdAt":"@string@.isDateTime()"
          }
        ],
        "hydra:totalItems":1,
        "hydra:search":{
          "@*@":"@*@"
        }
      }
      """

  Scenario: Import tasks with CSV format (async)
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
      | tags.yml            |
    Given the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "text/csv"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/tasks/import_async" with body:
      """
      type,address.streetAddress,address.telephone,address.name,after,before,tags
      pickup,"1, rue de Rivoli Paris",,Foo,2018-02-15 09:00,2018-02-15 10:00,"important"
      dropoff,"54, rue du Faubourg Saint Denis Paris",,Bar,2018-02-15 09:00,2018-02-15 10:00,"important fragile"
      dropoff,"68, rue du Faubourg Saint Denis Paris",,Baz,2018-02-15 10:00,2018-02-15 11:00,"fragile"
      dropoff,"42, rue de Rivoli Paris",,Bat,2018-02-15 11:30,2018-02-15 12:00,
      """
    Then the response status code should be 201
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/TaskImportQueue",
        "@id":"/api/task_import_queues/1",
        "@type":"TaskImportQueue"
      }
      """

  Scenario: Restore a cancelled task
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | dispatch.yml        |
    And the user "sarah" has role "ROLE_ADMIN"
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/tasks/1/cancel" with body:
    """
      {}
    """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"@string@.startsWith('/api/tasks')",
        "@type":"Task",
        "id":@integer@,
        "type":"DROPOFF",
        "status":"CANCELLED",
        "address":@...@,
        "after":"2018-12-01T10:30:00+01:00",
        "before":"2018-12-01T11:00:00+01:00",
        "doneAfter":"2018-12-01T10:30:00+01:00",
        "doneBefore":"2018-12-01T11:00:00+01:00",
        "comments":"",
        "updatedAt":"@string@.isDateTime()",
        "isAssigned":false,
        "assignedTo":null,
        "previous":null,
        "next":null,
        "group":null,
        "tags":@array@
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/tasks/1/restore" with body:
    """
      {}
    """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"@string@.startsWith('/api/tasks')",
        "@type":"Task",
        "id":@integer@,
        "type":"DROPOFF",
        "status":"TODO",
        "address":@...@,
        "after":"2018-12-01T10:30:00+01:00",
        "before":"2018-12-01T11:00:00+01:00",
        "doneAfter":"2018-12-01T10:30:00+01:00",
        "doneBefore":"2018-12-01T11:00:00+01:00",
        "comments":"",
        "updatedAt":"@string@.isDateTime()",
        "isAssigned":false,
        "assignedTo":null,
        "previous":null,
        "next":null,
        "group":null,
        "tags":@array@
      }
      """

  Scenario: Doesn't double package quantity
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | dispatch.yml        |
    And the user "sarah" has role "ROLE_ADMIN"
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/tasks/9" with body:
      """
      {
        "packages": [
          {
            "type":"SMALL",
            "quantity": 4
          }
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
         "packages":[
            {
               "type":"SMALL",
               "name":"SMALL",
               "quantity":4
            }
         ],
         "@*@": "@*@"
      }
      """

  Scenario: Can update address.name & comments
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    Given the store with name "Acme" has imported tasks:
      | type    | address.streetAddress                 | after            | before           |
      | pickup  | 1, rue de Rivoli Paris                | 2018-02-15 09:00 | 2018-02-15 10:00 |
      | dropoff | 54, rue du Faubourg Saint Denis Paris | 2018-02-15 09:00 | 2018-02-15 10:00 |
    Given the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "PUT" request to "/api/tasks/1/bio_deliver" with body:
      """
      {
        "address": {
          "name": "Foo"
        },
        "comments": "Lorem ipsum"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "address":{
          "name": "Foo",
          "@*@": "@*@"
        },
        "comments": "Lorem ipsum",
        "@*@": "@*@"
      }
      """

  Scenario: Mark multiple tasks as done
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/tasks/done" with body:
      """
      {
        "tasks": [
          "/api/tasks/1",
          "/api/tasks/2"
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
        {
          "success": [
            {
              "@id":"/api/tasks/1",
              "@type":"Task",
              "id":1,
              "status": "DONE",
              "@*@":"@*@"
            },
            {
              "@id":"/api/tasks/2",
              "@type":"Task",
              "id":2,
              "status": "DONE",
              "@*@":"@*@"
            }
          ],
          "failed": []
        }
      """

  Scenario: Mark one tasks as done and another one as failed
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/tasks/done" with body:
      """
      {
        "tasks": [
          "/api/tasks/1",
          "/api/tasks/5"
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
        {
          "success": [
            {
              "@id":"/api/tasks/1",
              "@type":"Task",
              "id":1,
              "status": "DONE",
              "@*@":"@*@"
            }
          ],
          "failed": {
            "/api/tasks/5": @string@
          }
        }
      """

  Scenario: Assign images to multiple tasks
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/tasks/images" with body:
      """
      {
        "tasks": [
          "/api/tasks/1",
          "/api/tasks/2"
        ],
        "images": [
          "/api/task_images/1",
          "/api/task_images/2"
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
        {
          "@context":"/api/contexts/Task",
          "@id":"/api/tasks",
          "@type":"hydra:Collection",
          "hydra:member": [
            {
              "@id":"/api/tasks/1",
              "@type":"Task",
              "id":1,
              "images": "@array@.count(2)",
              "@*@":"@*@"
            },
            {
              "@id":"/api/tasks/2",
              "@type":"Task",
              "id":2,
              "images": "@array@.count(2)",
              "@*@":"@*@"
            }
          ],
          "hydra:totalItems": 2,
          "@*@":"@*@"
        }
      """

  Scenario: Upload image
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "multipart/form-data"
    And the user "bob" sends a "POST" request to "/api/task_images" with parameters:
      | key      | value              |
      | file     | @beer.jpg |
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/TaskImage",
        "@id":@string@,
        "@type":"http://schema.org/MediaObject",
        "imageName":@string@,
        "thumbnail":@string@
      }
      """

  Scenario: Upload image with task in header
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "multipart/form-data"
    When I add "X-Attach-To" header equal to "/api/tasks/1"
    And the user "bob" sends a "POST" request to "/api/task_images" with parameters:
      | key      | value              |
      | file     | @beer.jpg |
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/TaskImage",
        "@id":@string@,
        "@type":"http://schema.org/MediaObject",
        "imageName":@string@,
        "thumbnail":@string@
      }
      """

  Scenario: Upload image with tasks in header
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "multipart/form-data"
    When I add "X-Attach-To" header equal to "/api/tasks/1;/api/tasks/2"
    And the user "bob" sends a "POST" request to "/api/task_images" with parameters:
      | key      | value              |
      | file     | @beer.jpg |
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/TaskImage",
        "@id":@string@,
        "@type":"http://schema.org/MediaObject",
        "imageName":@string@,
        "thumbnail":@string@
      }
      """

