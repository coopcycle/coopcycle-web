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
            "id": "@integer@",
            "@type":"TaskList",
            "items":@array@,
            "distance":0,
            "duration":0,
            "polyline":"",
            "createdAt":"@string@.isDateTime()",
            "updatedAt":"@string@.isDateTime()",
            "username":"sarah",
            "date":"2018-12-01",
            "vehicle": null,
            "trailer": null
          },
          {
            "@id":"@string@.startsWith('/api/task_lists')",
            "id": "@integer@",
            "@type":"TaskList",
            "items":@array@,
            "distance":0,
            "duration":0,
            "polyline":"",
            "createdAt":"@string@.isDateTime()",
            "updatedAt":"@string@.isDateTime()",
            "username":"bob",
            "date":"2018-12-01",
            "vehicle": null,
            "trailer": null
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
        "address":{"@*@":"@*@"},
        "doneAfter":"@string@.isDateTime()",
        "doneBefore":"@string@.isDateTime()",
        "comments":"",
        "updatedAt":"@string@.isDateTime()",
        "isAssigned":true,
        "assignedTo":"sarah",
        "previous":null,
        "next":null,
        "group":null,
        "tags":@array@,
        "createdAt":"@string@.isDateTime()",
        "doorstep": @boolean@,
        "ref": null,
        "recurrenceRule": null,
        "metadata": @array@,
        "weight": null,
        "incidents": @array@,
        "after":"@string@.isDateTime()",
        "before":"@string@.isDateTime()",
        "orgName": @string@,
        "images": @array@,
        "hasIncidents": @boolean@
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
        "hydra:description":"Task #4 is already assigned to \u0022bob\u0022",
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

  Scenario: Create task group
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

  Scenario: Retrieve task lists as OAuth client
    Given the fixtures files are loaded:
      | stores_with_orgs.yml |
      | tasks_with_orgs.yml  |
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "GET" request to "/api/task_lists?date=2018-12-01"
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
            "@id":"/api/task_lists/1",
            "id": "@integer@",
            "@type":"TaskList",
            "items":[
              {
                "@id":"/api/tasks/1",
                "@type":"Task",
                "id":1,
                "type":"DROPOFF",
                "@*@": "@*@"
              }
            ],
            "distance":0,
            "duration":0,
            "polyline":"iyfiH}tfM??",
            "date":"2018-12-01",
            "username":"sarah",
            "createdAt":"@string@.isDateTime()",
            "updatedAt":"@string@.isDateTime()",
            "vehicle": null,
            "trailer": null
          },
          {
            "@id":"/api/task_lists/2",
            "id": "@integer@",
            "@type":"TaskList",
            "items":[
              {
                "@id":"/api/tasks/3",
                "@type":"Task",
                "id":3,
                "type":"DROPOFF",
                "@*@": "@*@"
              }
            ],
            "distance":0,
            "duration":0,
            "polyline":"iyfiH}tfM??",
            "date":"2018-12-01",
            "username":"bob",
            "createdAt":"@string@.isDateTime()",
            "updatedAt":"@string@.isDateTime()",
            "vehicle": null,
            "trailer": null
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
          "hydra:mapping":[
            {
              "@type":"IriTemplateMapping",
              "variable":"date",
              "property":"date",
              "required":false
            }
          ]
        }
      }
      """

  Scenario: Retrieve tasks filtered by organization
    Given the fixtures files are loaded:
      | stores_with_orgs.yml |
      | tasks_with_orgs.yml  |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/tasks?date=2018-12-01&organization=Acme"
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
            "id":@integer@,
            "type":"DROPOFF",
            "status":"TODO",
            "address":{
              "@id":"/api/addresses/1",
              "@*@":"@*@"
            },
            "doneAfter":"@string@.isDateTime()",
            "doneBefore":"@string@.isDateTime()",
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":true,
            "orgName":"Acme",
            "assignedTo":"bob",
            "@*@":"@*@"
          },
          {
            "id":@integer@,
            "type":"DROPOFF",
            "status":"TODO",
            "address":{
              "@id":"/api/addresses/1",
              "@*@":"@*@"
            },
            "doneAfter":"@string@.isDateTime()",
            "doneBefore":"@string@.isDateTime()",
            "updatedAt":"@string@.isDateTime()",
            "isAssigned":true,
            "orgName":"Acme",
            "assignedTo":"sarah",
            "@*@":"@*@"
          }
        ],
        "hydra:totalItems":2,
        "@*@":"@*@"
      }
      """

  Scenario: Create delivery from tasks
    Given the fixtures files are loaded:
      | dispatch.yml        |
    And the user "sarah" has role "ROLE_ADMIN"
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/deliveries/from_tasks" with body:
      """
      {
        "tasks": [
          "/api/tasks/4",
          "/api/tasks/5"
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
         "@context":"/api/contexts/Delivery",
         "@id":@string@,
         "@type":"http://schema.org/ParcelDelivery",
         "id":@integer@,
         "pickup":{
            "@id":"/api/tasks/4",
            "@type":"Task",
            "id":4,
            "@*@":"@*@"
         },
         "dropoff":{
            "@id":"/api/tasks/5",
            "@type":"Task",
            "id":5,
            "@*@":"@*@"
         },
         "trackingUrl":@string@
      }
      """

  Scenario: Create delivery from tasks (multiple)
    Given the fixtures files are loaded:
      | dispatch.yml        |
    And the user "sarah" has role "ROLE_ADMIN"
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/deliveries/from_tasks" with body:
      """
      {
        "tasks": [
          "/api/tasks/4",
          "/api/tasks/5",
          "/api/tasks/8"
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
         "@context":"/api/contexts/Delivery",
         "@id":@string@,
         "@type":"http://schema.org/ParcelDelivery",
         "id":@integer@,
         "pickup":{
            "@id":"/api/tasks/4",
            "@type":"Task",
            "id":4,
            "@*@":"@*@"
         },
         "dropoff":{
            "@id":"/api/tasks/8",
            "@type":"Task",
            "id":8,
            "@*@":"@*@"
         },
         "trackingUrl":@string@
      }
      """

  Scenario: Create tour from tasks
    Given the fixtures files are loaded:
      | dispatch.yml        |
    And the user "sarah" has role "ROLE_ADMIN"
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/tours" with body:
      """
      {
        "name":"Monday tour",
        "date": "2018-02-02",
        "tasks":[
          "/api/tasks/4",
          "/api/tasks/5"
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
         "@context":"/api/contexts/Tour",
         "@id":"@string@.startsWith('/api/tours')",
         "@type":"Tour",
         "name":"Monday tour",
         "date": "2018-02-02",
         "items":[
          "/api/tasks/4",
          "/api/tasks/5"
        ],
         "distance":@integer@,
         "duration":@integer@,
         "polyline":@string@,
         "createdAt":"@string@.isDateTime()",
         "updatedAt":"@string@.isDateTime()"
      }
      """

  Scenario: Add/reorder tasks of a tour
    Given the fixtures files are loaded:
      | dispatch.yml |
      | tours.yml    |
    And the user "sarah" has role "ROLE_ADMIN"
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/tours/1" with body:
      """
      {
        "name":"Monday tour",
        "tasks":[
          "/api/tasks/3",
          "/api/tasks/2",
          "/api/tasks/1"
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
         "@context":"/api/contexts/Tour",
         "@id":"/api/tours/1",
         "@type":"Tour",
         "name":"Monday tour",
         "date": "2018-03-02",
         "items":[
          "/api/tasks/3",
          "/api/tasks/2",
          "/api/tasks/1"
        ],
         "distance":@integer@,
         "duration":@integer@,
         "polyline":@string@,
         "createdAt":"@string@.isDateTime()",
         "updatedAt":"@string@.isDateTime()"
      }
      """


  Scenario: Administrator can assign multiple tasks at once
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | dispatch.yml        |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/tasks/assign" with body:
      """
      {
        "username": "sarah",
        "tasks": [
          "/api/tasks/8",
          "/api/tasks/9"
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
            "@id":"/api/tasks/8",
            "@type":"Task",
            "id":8,
            "isAssigned":true,
            "assignedTo":"sarah",
            "@*@":"@*@"
          },
          {
            "@id":"/api/tasks/9",
            "@type":"Task",
            "id":9,
            "isAssigned":true,
            "assignedTo":"sarah",
            "@*@":"@*@"
          }
        ],
        "hydra:totalItems": 2,
        "@*@":"@*@"
      }
      """
