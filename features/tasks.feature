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
            "doneAfter":"2018-03-02T11:30:00+00:00",
            "doneBefore":"2018-03-02T12:00:00+00:00",
            "comments":"#bob",
            "events":@array@,
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
            "doneAfter":"2018-03-02T12:00:00+00:00",
            "doneBefore":"2018-03-02T12:30:00+00:00",
            "comments":"#bob",
            "events":@array@,
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":true,
            "assignedTo":"bob",
            "previous":null,
            "group":null,
            "tags":@array@
          }
        ],
        "hydra:totalItems":2,
        "hydra:search":{
          "@type":"hydra:IriTemplate",
          "hydra:template":"/api/me/tasks/2018-03-02{?date,assigned}",
          "hydra:variableRepresentation":"BasicRepresentation",
          "hydra:mapping":@array@
        }
      }
      """

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
        "doneAfter":"2018-03-02T11:30:00+01:00",
        "doneBefore":"2018-03-02T12:00:00+01:00",
        "comments":@string@,
        "events":@array@,
        "updatedAt":"@string@.isDateTime()",
        "isAssigned":true,
        "assignedTo":"bob",
        "previous":null,
        "group":null,
        "tags":@array@
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
        "doneAfter":"2018-03-02T11:30:00+01:00",
        "doneBefore":"2018-03-02T12:00:00+01:00",
        "comments":@string@,
        "events":[
          {
            "name":"ASSIGN",
            "notes":null,
            "createdAt":@string@
          },
          {
            "name":"CREATE",
            "notes":null,
            "createdAt":@string@
          },
          {
            "name":"FAILED",
            "notes":"Address not found",
            "createdAt":@string@
          }
        ],
        "updatedAt":"@string@.isDateTime()",
        "isAssigned":true,
        "assignedTo":"bob",
        "previous":null,
        "group":null,
        "tags":@array@
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
          "floor":null,
          "geo":{
            "latitude":48.870473,
            "longitude":2.331933
          },
          "streetAddress":"101 Rue de la Paix, 75002 Paris",
          "telephone":"+33612345678",
          "name":null
        },
        "doneAfter":"2018-12-24T23:30:00+01:00",
        "doneBefore":"2018-12-24T23:59:59+01:00",
        "comments":"",
        "events":@array@,
        "updatedAt":"@string@.isDateTime()",
        "isAssigned":false,
        "assignedTo":null,
        "previous":null,
        "next":null,
        "deliveryColor":null,
        "group":null,
        "tags":@array@
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
          "floor":null,
          "geo":{
            "latitude":48.870473,
            "longitude":2.331933
          },
          "streetAddress":"101 Rue de la Paix, 75002 Paris",
          "telephone":null,
          "name":null
        },
        "doneAfter":"2018-12-24T23:30:00+01:00",
        "doneBefore":"2018-12-24T23:59:59+01:00",
        "comments":"Hello, world",
        "events":@array@,
        "updatedAt":"@string@.isDateTime()",
        "isAssigned":false,
        "assignedTo":null,
        "previous":null,
        "next":null,
        "deliveryColor":null,
        "group":null,
        "tags": [
          {"name":"Important","slug":"important","color":"#000000"}
        ]
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
            "doneAfter":"2018-12-01T10:30:00+01:00",
            "doneBefore":"2018-12-01T11:00:00+01:00",
            "comments":null,
            "events":@array@,
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":true,
            "assignedTo":"sarah",
            "previous":null,
            "next":null,
            "deliveryColor":null,
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
            "doneAfter":"2018-12-01T11:30:00+01:00",
            "doneBefore":"2018-12-01T12:00:00+01:00",
            "comments":null,
            "events":@array@,
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":true,
            "assignedTo":"sarah",
            "previous":null,
            "next":null,
            "deliveryColor":null,
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
            "doneAfter":"2018-12-01T10:30:00+01:00",
            "doneBefore":"2018-12-01T11:00:00+01:00",
            "comments":null,
            "events":@array@,
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":true,
            "assignedTo":"sarah",
            "previous":null,
            "next":null,
            "deliveryColor":null,
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
            "doneAfter":"2018-12-01T11:30:00+01:00",
            "doneBefore":"2018-12-01T12:00:00+01:00",
            "comments":null,
            "events":@array@,
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":true,
            "assignedTo":"sarah",
            "previous":null,
            "next":null,
            "deliveryColor":null,
            "group":null,
            "tags":@array@
          },
          {
            "@id":"/api/tasks/5",
            "@type":"Task",
            "id":5,
            "type":"DROPOFF",
            "status":"TODO",
            "address":@...@,
            "doneAfter":"2018-12-01T13:00:00+01:00",
            "doneBefore":"2018-12-01T13:30:00+01:00",
            "comments":null,
            "events":@array@,
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":true,
            "assignedTo":"bob",
            "previous":null,
            "next":null,
            "deliveryColor":null,
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
            "comments":null,
            "events":@array@,
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":false,
            "assignedTo":null,
            "previous":null,
            "next":null,
            "deliveryColor":null,
            "group":null,
            "tags":@array@
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
            "doneAfter":"2018-12-01T12:00:00+01:00",
            "doneBefore":"2018-12-01T12:30:00+01:00",
            "comments":null,
            "events":@array@,
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":false,
            "assignedTo":null,
            "previous":null,
            "next":null,
            "deliveryColor":null,
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
        "@id":"/api/tasks/7",
        "@type":"Task",
        "id":7,
        "type":"DROPOFF",
        "status":"TODO",
        "address":@...@,
        "doneAfter":"2018-12-01T10:30:00+01:00",
        "doneBefore":"2018-12-01T11:00:00+01:00",
        "comments":"",
        "events":@array@,
        "updatedAt":"@string@.isDateTime()",
        "isAssigned":false,
        "assignedTo":null,
        "previous":null,
        "next":null,
        "deliveryColor":null,
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
