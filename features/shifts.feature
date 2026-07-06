Feature: Shifts

  Scenario: Dispatcher creates a shift with assignees
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the courier "alice" is loaded:
      | email    | alice@coopcycle.org |
      | password | 123456              |
    And the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shifts" with body:
      """
      {
        "type": "drive",
        "startsAt": "2026-06-29T09:00:00",
        "endsAt": "2026-06-29T17:00:00",
        "slots": 2,
        "users": ["/api/users/1", "/api/users/2"]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Shift",
        "@id":"/api/shifts/1",
        "@type":"Shift",
        "id":1,
        "type":"drive",
        "startsAt":"@string@.isDateTime()",
        "endsAt":"@string@.isDateTime()",
        "slots":2,
        "assignments":[
          {
            "@id":"@string@",
            "@type":"ShiftAssignment",
            "user":{
              "@id":"/api/users/1",
              "@type":"User",
              "username":"sarah"
            },
            "createdAt":"@string@.isDateTime()"
          },
          {
            "@id":"@string@",
            "@type":"ShiftAssignment",
            "user":{
              "@id":"/api/users/2",
              "@type":"User",
              "username":"alice"
            },
            "createdAt":"@string@.isDateTime()"
          }
        ]
      }
      """

  Scenario: Courier can not create or list shifts
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/shifts" with body:
      """
      {
        "type": "drive",
        "startsAt": "2026-06-29T09:00:00",
        "endsAt": "2026-06-29T17:00:00"
      }
      """
    Then the response status code should be 403
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "GET" request to "/api/shifts"
    Then the response status code should be 403

  Scenario: Courier retrieves own shifts
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the courier "alice" is loaded:
      | email    | alice@coopcycle.org |
      | password | 123456              |
    And the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shifts" with body:
      """
      {
        "type": "drive",
        "startsAt": "2026-06-29T09:00:00",
        "endsAt": "2026-06-29T17:00:00",
        "users": ["/api/users/1"]
      }
      """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shifts" with body:
      """
      {
        "type": "dispatch",
        "startsAt": "2026-06-30T09:00:00",
        "endsAt": "2026-06-30T17:00:00",
        "users": ["/api/users/2"]
      }
      """
    Then the response status code should be 201
    Given the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "GET" request to "/api/me/shifts?date[after]=2026-06-29&date[before]=2026-07-05"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Shift",
        "@id":"/api/me/shifts",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/shifts/1",
            "@type":"Shift",
            "id":1,
            "type":"drive",
            "startsAt":"@string@.isDateTime()",
            "endsAt":"@string@.isDateTime()",
            "slots":1,
            "assignments":"@array@"
          }
        ],
        "hydra:totalItems":1,
        "hydra:view":"@*@",
        "hydra:search":"@*@"
      }
      """

  Scenario: Dispatcher updates shift assignees
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the courier "alice" is loaded:
      | email    | alice@coopcycle.org |
      | password | 123456              |
    And the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shifts" with body:
      """
      {
        "type": "drive",
        "startsAt": "2026-06-29T09:00:00",
        "endsAt": "2026-06-29T17:00:00",
        "users": ["/api/users/1"]
      }
      """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/shifts/1" with body:
      """
      {
        "type": "drive",
        "startsAt": "2026-06-29T09:00:00",
        "endsAt": "2026-06-29T17:00:00",
        "users": ["/api/users/2"]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Shift",
        "@id":"/api/shifts/1",
        "@type":"Shift",
        "id":1,
        "type":"drive",
        "startsAt":"@string@.isDateTime()",
        "endsAt":"@string@.isDateTime()",
        "slots":1,
        "assignments":[
          {
            "@id":"@string@",
            "@type":"ShiftAssignment",
            "user":{
              "@id":"/api/users/2",
              "@type":"User",
              "username":"alice"
            },
            "createdAt":"@string@.isDateTime()"
          }
        ]
      }
      """

  Scenario: Courier requests a holiday, dispatcher approves it
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/holiday_requests" with body:
      """
      {
        "startDate": "2026-07-06",
        "endDate": "2026-07-10",
        "comment": "Summer break"
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/HolidayRequest",
        "@id":"/api/holiday_requests/1",
        "@type":"HolidayRequest",
        "id":1,
        "user":{
          "@id":"/api/users/1",
          "@type":"User",
          "username":"sarah"
        },
        "startDate":"@string@.isDateTime()",
        "endDate":"@string@.isDateTime()",
        "status":"pending",
        "comment":"Summer break",
        "actionedBy":null,
        "actionedAt":null,
        "createdAt":"@string@.isDateTime()"
      }
      """
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/holiday_requests/1/approve" with body:
      """
      {}
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/HolidayRequest",
        "@id":"@string@",
        "@type":"HolidayRequest",
        "id":1,
        "user":{
          "@id":"/api/users/1",
          "@type":"User",
          "username":"sarah"
        },
        "startDate":"@string@.isDateTime()",
        "endDate":"@string@.isDateTime()",
        "status":"approved",
        "comment":"Summer break",
        "actionedBy":{
          "@id":"/api/users/2",
          "@type":"User",
          "username":"bob"
        },
        "actionedAt":"@string@.isDateTime()",
        "createdAt":"@string@.isDateTime()"
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/holiday_requests/1/approve" with body:
      """
      {}
      """
    Then the response status code should be 400

  Scenario: Courier can not approve own holiday request
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/holiday_requests" with body:
      """
      {
        "startDate": "2026-07-06",
        "endDate": "2026-07-10"
      }
      """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/holiday_requests/1/approve" with body:
      """
      {}
      """
    Then the response status code should be 403
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "GET" request to "/api/me/holiday_requests"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/HolidayRequest",
        "@id":"/api/me/holiday_requests",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/holiday_requests/1",
            "@type":"HolidayRequest",
            "id":1,
            "user":"@*@",
            "startDate":"@string@.isDateTime()",
            "endDate":"@string@.isDateTime()",
            "status":"pending",
            "comment":null,
            "actionedBy":null,
            "actionedAt":null,
            "createdAt":"@string@.isDateTime()"
          }
        ],
        "hydra:totalItems":1,
        "hydra:search":"@*@"
      }
      """

  Scenario: Copying a week skips users on approved holiday
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the courier "alice" is loaded:
      | email    | alice@coopcycle.org |
      | password | 123456              |
    And the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shifts" with body:
      """
      {
        "type": "drive",
        "startsAt": "2026-06-23T09:00:00",
        "endsAt": "2026-06-23T17:00:00",
        "slots": 2,
        "users": ["/api/users/1", "/api/users/2"]
      }
      """
    Then the response status code should be 201
    Given the user "alice" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "alice" sends a "POST" request to "/api/holiday_requests" with body:
      """
      {
        "startDate": "2026-06-29",
        "endDate": "2026-07-03"
      }
      """
    Then the response status code should be 201
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/holiday_requests/1/approve" with body:
      """
      {}
      """
    Then the response status code should be 200
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shifts/copy_week" with body:
      """
      {
        "sourceWeek": "2026-06-22",
        "targetWeek": "2026-06-29"
      }
      """
    Then the response status code should be 204
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/shifts?date[after]=2026-06-29&date[before]=2026-07-05"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Shift",
        "@id":"/api/shifts",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/shifts/2",
            "@type":"Shift",
            "id":2,
            "type":"drive",
            "startsAt":"@string@.isDateTime()",
            "endsAt":"@string@.isDateTime()",
            "slots":2,
            "assignments":[
              {
                "@id":"@string@",
                "@type":"ShiftAssignment",
                "user":{
                  "@id":"/api/users/1",
                  "@type":"User",
                  "username":"sarah"
                },
                "createdAt":"@string@.isDateTime()"
              }
            ]
          }
        ],
        "hydra:totalItems":1,
        "hydra:view":"@*@",
        "hydra:search":"@*@"
      }
      """

  Scenario: Assigning a courier to a shift adds them to the dispatch
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shifts" with body:
      """
      {
        "type": "drive",
        "startsAt": "2026-06-29T09:00:00",
        "endsAt": "2026-06-29T17:00:00",
        "users": ["/api/users/1"]
      }
      """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/task_lists?date=2026-06-29"
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
            "username":"sarah",
            "date":"2026-06-29",
            "items":[],
            "@*@":"@*@"
          }
        ],
        "hydra:totalItems":1,
        "hydra:view":"@*@",
        "hydra:search":"@*@"
      }
      """

  Scenario: Dispatcher customizes shift type colors
    Given the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/shift_settings"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ShiftSettings",
        "@id":"/api/shift_settings",
        "@type":"ShiftSettings",
        "typeColors":[],
        "throughput":@double@,
        "serviceLevel":@double@
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/shift_settings" with body:
      """
      {
        "typeColors": {
          "drive": "#ff0000",
          "dispatch": "not-a-color"
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ShiftSettings",
        "@id":"/api/shift_settings",
        "@type":"ShiftSettings",
        "typeColors":{
          "drive":"#ff0000"
        },
        "throughput":@double@,
        "serviceLevel":@double@
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/shift_settings"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ShiftSettings",
        "@id":"/api/shift_settings",
        "@type":"ShiftSettings",
        "typeColors":{
          "drive":"#ff0000"
        },
        "throughput":@double@,
        "serviceLevel":@double@
      }
      """

  Scenario: Courier can read but not change shift settings
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "GET" request to "/api/shift_settings"
    Then the response status code should be 200
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/shift_settings" with body:
      """
      {
        "typeColors": { "drive": "#ff0000" }
      }
      """
    Then the response status code should be 403

  Scenario: Dispatcher generates a schedule suggestion from demand
    Given the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shifts/generate_schedule" with body:
      """
      {
        "week": "2026-07-13"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"@string@",
        "@id":"@string@",
        "@type":"ShiftScheduleSuggestion",
        "shifts":@array@,
        "days":@array@,
        "meta":{
          "lookbackWeeks":@integer@,
          "serviceLevel":@double@,
          "throughput":@double@,
          "observations":@integer@
        }
      }
      """

  Scenario: Courier can not generate a schedule
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/shifts/generate_schedule" with body:
      """
      { "week": "2026-07-13" }
      """
    Then the response status code should be 403

  Scenario: Dispatcher batch-creates shifts
    Given the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shifts/batch" with body:
      """
      {
        "shifts": [
          { "type": "drive", "startsAt": "2026-07-13T12:00:00", "endsAt": "2026-07-13T15:00:00", "slots": 2 },
          { "type": "drive", "startsAt": "2026-07-13T19:00:00", "endsAt": "2026-07-13T22:00:00", "slots": 1 }
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"@string@",
        "@id":"@string@",
        "@type":"ShiftBatch",
        "created":2
      }
      """
