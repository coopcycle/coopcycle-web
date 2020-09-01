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
        "@context":"/api/contexts/Task",
        "@id":"/api/tasks",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"@string@.startsWith('/api/tasks')",
            "@type":"Task",
            "id":@integer@,
            "type":"DROPOFF",
            "status":"TODO",
            "address":@...@,
            "after":"2018-03-02T11:30:00+00:00",
            "before":"2018-03-02T12:00:00+00:00",
            "doneAfter":"2018-03-02T11:30:00+00:00",
            "doneBefore":"2018-03-02T12:00:00+00:00",
            "comments":"#bob",
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":true,
            "assignedTo":"bob",
            "previous":null,
            "group":null,
            "tags":@array@
          },
          {
            "@id":"@string@.startsWith('/api/tasks')",
            "@type":"Task",
            "id":@integer@,
            "type":"DROPOFF",
            "status":"DONE",
            "address":@...@,
            "after":"2018-03-02T12:00:00+00:00",
            "before":"2018-03-02T12:30:00+00:00",
            "doneAfter":"2018-03-02T12:00:00+00:00",
            "doneBefore":"2018-03-02T12:30:00+00:00",
            "comments":"#bob",
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":true,
            "assignedTo":"bob",
            "previous":null,
            "group":null,
            "tags":@array@
          }
        ],
        "hydra:totalItems":2
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
        "@context":"/api/contexts/TaskEvent",
        "@id":"/api/tasks/2/events",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"@string@.startsWith('/api/task_events')",
            "@type":"TaskEvent",
            "id":@integer@,
            "task":"/api/tasks/2",
            "name":"task:created",
            "data":[],
            "metadata":[],
            "createdAt":"@string@.isDateTime()"
          },
          {
            "@id":"@string@.startsWith('/api/task_events')",
            "@type":"TaskEvent",
            "id":@integer@,
            "task":"/api/tasks/2",
            "name":"task:assigned",
            "data":{
              "username":"bob"
            },
            "metadata":[],
            "createdAt":"@string@.isDateTime()"
          }
        ],
        "hydra:totalItems":2
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
        "doorstep":false
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
        "doneBefore": "2018-12-24T23:59:59+01:00"
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
        "doorstep":false
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
        "doorstep":false
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
            "tags":[]
          },
          {
            "@id":"/api/tasks/2",
            "@type":"Task",
            "id":@integer@,
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
            "tags":[]
          }
        ],
        "hydra:totalItems":2,
        "hydra:view":{
          "@id":"/api/tasks?date=2018-12-01",
          "@type":"hydra:PartialCollectionView"
        },
        "hydra:search":{
          "@type":"hydra:IriTemplate",
          "hydra:template":"/api/tasks{?date,assigned}",
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
          "hydra:template":"/api/tasks{?date,assigned}",
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
          "hydra:template":"/api/tasks{?date,assigned}",
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
            "message":@string@
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
      | dispatch.yml        |
      | deliveries.yml      |
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "PUT" request to "/api/deliveries/5" with body:
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
        "@id":"/api/deliveries/5",
        "@type":"http://schema.org/ParcelDelivery",
        "id":5,
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
        }
      }
      """

  Scenario: Can complete pickup & dropoff
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | dispatch.yml        |
      | deliveries.yml      |
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "PUT" request to "/api/deliveries/5/pick" with body:
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
        "@id":"/api/deliveries/5",
        "@type":"http://schema.org/ParcelDelivery",
        "id":5,
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
        }
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "PUT" request to "/api/deliveries/5/drop" with body:
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
        "@id":"/api/deliveries/5",
        "@type":"http://schema.org/ParcelDelivery",
        "id":5,
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
        }
      }
      """

  Scenario: Import tasks with CSV format
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    Given the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "text/csv"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/tasks/import" with body:
      """
      type,address.streetAddress,address.telephone,address.name,after,before
      pickup,"1, rue de Rivoli Paris",,Foo,2018-02-15 09:00,2018-02-15 10:00
      dropoff,"54, rue du Faubourg Saint Denis Paris",,Bar,2018-02-15 09:00,2018-02-15 10:00
      """
    Then the response status code should be 201
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/TaskGroup",
        "@id":"/api/task_groups/1",
        "@type":"TaskGroup",
        "id":@integer@,
        "name":@string@,
        "tags":[]
      }
      """

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
         "@context":"\/api\/contexts\/ConstraintViolationList",
         "@type":"ConstraintViolationList",
         "hydra:title":"An error occurred",
         "hydra:description":@string@,
         "violations":[
            {
               "propertyPath":"address.geo",
               "message":@string@
            },
            {
               "propertyPath":"address.streetAddress",
               "message":@string@
            }
         ]
      }
      """
