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
        "breakMinutes": 30,
        "comment": "Bring your own bike lock",
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
        "breakMinutes":30,
        "comment":"Bring your own bike lock",
        "requiredSkills":[],
        "waitlist":[],
        "assignments":[
          {
            "@id":"@string@",
            "@type":"ShiftAssignment",
            "user":{
              "@id":"/api/users/1",
              "@type":"User",
              "username":"sarah"
            },
            "createdAt":"@string@.isDateTime()",
            "adjustment":"@*@"
          },
          {
            "@id":"@string@",
            "@type":"ShiftAssignment",
            "user":{
              "@id":"/api/users/2",
              "@type":"User",
              "username":"alice"
            },
            "createdAt":"@string@.isDateTime()",
            "adjustment":"@*@"
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
        "comment": "Bring your own bike lock",
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
            "breakMinutes":0,
            "comment":"Bring your own bike lock",
            "requiredSkills":[],
        "waitlist":[],
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
        "breakMinutes":0,
        "comment":null,
        "requiredSkills":[],
        "waitlist":[],
        "assignments":[
          {
            "@id":"@string@",
            "@type":"ShiftAssignment",
            "user":{
              "@id":"/api/users/2",
              "@type":"User",
              "username":"alice"
            },
            "createdAt":"@string@.isDateTime()",
            "adjustment":"@*@"
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
            "breakMinutes":0,
            "comment":null,
            "requiredSkills":[],
        "waitlist":[],
            "assignments":[
              {
                "@id":"@string@",
                "@type":"ShiftAssignment",
                "user":{
                  "@id":"/api/users/1",
                  "@type":"User",
                  "username":"sarah"
                },
                "createdAt":"@string@.isDateTime()",
                "adjustment":"@*@"
              }
            ]
          }
        ],
        "hydra:totalItems":1,
        "hydra:view":"@*@",
        "hydra:search":"@*@"
      }
      """

  Scenario: Assigning a courier to a shift does NOT automatically add them to the dispatch
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
        "hydra:member":[],
        "hydra:totalItems":0,
        "hydra:view":"@*@",
        "hydra:search":"@*@"
      }
      """

  Scenario: Dispatcher manually adds shift-assigned couriers to the dispatch
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
    And the user "bob" sends a "POST" request to "/api/shifts/dispatch_sync" with body:
      """
      { "week": "2026-06-29" }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"@string@",
        "@id":"@string@",
        "@type":"ShiftDispatchSync",
        "added":1
      }
      """
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
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shifts/dispatch_sync" with body:
      """
      { "week": "2026-06-29" }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"@string@",
        "@id":"@string@",
        "@type":"ShiftDispatchSync",
        "added":0
      }
      """

  Scenario: Courier can not manually sync the dispatch
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/shifts/dispatch_sync" with body:
      """
      { "week": "2026-06-29" }
      """
    Then the response status code should be 403

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
        "serviceLevel":@double@,
        "legal":{"template":null,"rules":"@*@"},
        "legalTemplates":"@*@"
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
        "serviceLevel":@double@,
        "legal":{"template":null,"rules":"@*@"},
        "legalTemplates":"@*@"
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
        "serviceLevel":@double@,
        "legal":{"template":null,"rules":"@*@"},
        "legalTemplates":"@*@"
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
          "observations":@integer@,
          "forecaster":@string@
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

  Scenario: Dispatcher views the shifts dashboard fill rate by week
    Given the current time is "2026-06-29 10:00:00"
    And the courier "sarah" is loaded:
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
        "users": ["/api/users/1"]
      }
      """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shifts" with body:
      """
      {
        "type": "drive",
        "startsAt": "2026-07-06T09:00:00",
        "endsAt": "2026-07-06T17:00:00",
        "slots": 4,
        "users": ["/api/users/1", "/api/users/2"]
      }
      """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/shifts/dashboard?weeks=3"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"@string@",
        "@id":"@string@",
        "@type":"ShiftDashboard",
        "weeks":[
          {
            "weekStart":"2026-06-29",
            "weekEnd":"2026-07-05",
            "totalSlots":2,
            "totalAssignments":1,
            "fillRate":0.5,
            "published":false
          },
          {
            "weekStart":"2026-07-06",
            "weekEnd":"2026-07-12",
            "totalSlots":4,
            "totalAssignments":2,
            "fillRate":0.5,
            "published":false
          },
          {
            "weekStart":"2026-07-13",
            "weekEnd":"2026-07-19",
            "totalSlots":0,
            "totalAssignments":0,
            "fillRate":0,
            "published":false
          }
        ]
      }
      """

  Scenario: Courier can not view the shifts dashboard
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "GET" request to "/api/shifts/dashboard"
    Then the response status code should be 403

  Scenario: Courier subscribes to their shift calendar feed
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
        "startsAt": "2026-07-13T09:00:00",
        "endsAt": "2026-07-13T17:00:00",
        "slots": 1,
        "breakMinutes": 30,
        "comment": "Bring your bike; and a lock",
        "users": ["/api/users/1"]
      }
      """
    Then the response status code should be 201
    Given the user "sarah" is authenticated
    When I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "GET" request to "/api/me/shift_calendar"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"@string@",
        "@id":"@string@",
        "@type":"ShiftCalendar",
        "feedUrl":"@string@.contains('/calendar/shifts/')"
      }
      """
    When the user "sarah" requests their shift calendar feed
    Then the response status code should be 200
    And the response should contain "BEGIN:VCALENDAR"
    And the response should contain "BEGIN:VEVENT"
    And the response should contain "DTSTART:20260713T"
    And the response should contain "Bring your bike\; and a lock"
    When the user "sarah" requests their shift calendar feed with an invalid token
    Then the response status code should be 404

  Scenario: Dispatcher configures legal constraints and sees violations
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
    And the user "bob" sends a "PUT" request to "/api/shift_settings" with body:
      """
      {
        "typeColors": {},
        "legal": {
          "template": "ccn_transport_fr",
          "rules": { "maxDailyHours": 11 }
        }
      }
      """
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ShiftSettings",
        "@id":"/api/shift_settings",
        "@type":"ShiftSettings",
        "typeColors":[],
        "throughput":@double@,
        "serviceLevel":@double@,
        "legal":{
          "template":"ccn_transport_fr",
          "rules":{"maxDailyHours":11}
        },
        "legalTemplates":"@*@"
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shifts" with body:
      """
      {
        "type": "drive",
        "startsAt": "2026-07-20T06:00:00",
        "endsAt": "2026-07-20T19:00:00",
        "breakMinutes": 30,
        "users": ["/api/users/1"]
      }
      """
    Then the response status code should be 201
    When I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/shifts/compliance?week=2026-07-22"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"@string@",
        "@id":"@string@",
        "@type":"ShiftCompliance",
        "week":"2026-07-20",
        "template":"ccn_transport_fr",
        "violations":[
          {
            "username":"sarah",
            "rule":"maxDailyHours",
            "date":"2026-07-20",
            "actual":12.5,
            "limit":11
          }
        ]
      }
      """

  Scenario: Compliance check is empty when no template is configured
    Given the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/shifts/compliance?week=2026-07-22"
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"@string@",
        "@id":"@string@",
        "@type":"ShiftCompliance",
        "week":"2026-07-20",
        "template":null,
        "violations":[]
      }
      """

  Scenario: Courier can not read the compliance check
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "GET" request to "/api/shifts/compliance?week=2026-07-22"
    Then the response status code should be 403

  Scenario: Employee reports actual worked time, dispatcher adjusts it, employee reverts it
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
        "startsAt": "2026-07-13T09:00:00",
        "endsAt": "2026-07-13T17:00:00",
        "breakMinutes": 30,
        "users": ["/api/users/1"]
      }
      """
    Then the response status code should be 201
    Given the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/shifts/1/report_time" with body:
      """
      {
        "startsAt": "2026-07-13T09:00:00",
        "endsAt": "2026-07-13T18:30:00",
        "breakMinutes": 30,
        "comment": "Big delivery batch, stayed late"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Shift",
        "@id":"@string@",
        "@type":"Shift",
        "id":1,
        "type":"drive",
        "startsAt":"@string@.isDateTime()",
        "endsAt":"@string@.isDateTime()",
        "slots":1,
        "breakMinutes":30,
        "comment":null,
        "requiredSkills":[],
        "waitlist":[],
        "assignments":[
          {
            "@id":"@string@",
            "@type":"ShiftAssignment",
            "user":{
              "@id":"/api/users/1",
              "@type":"User",
              "username":"sarah"
            },
            "createdAt":"@string@.isDateTime()",
            "adjustment":{
              "@id":"@string@",
              "@type":"ShiftTimeAdjustment",
              "startsAt":"@string@.contains('2026-07-13T09:00:00')",
              "endsAt":"@string@.contains('2026-07-13T18:30:00')",
              "breakMinutes":30,
              "comment":"Big delivery batch, stayed late",
              "reportedBy":{
                "@id":"/api/users/1",
                "@type":"User",
                "username":"sarah"
              },
              "updatedAt":"@string@.isDateTime()"
            }
          }
        ]
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/shifts/1/report_time" with body:
      """
      {
        "user": "/api/users/1",
        "startsAt": "2026-07-13T09:00:00",
        "endsAt": "2026-07-13T18:00:00",
        "breakMinutes": 45
      }
      """
    Then the response status code should be 200
    And the response should contain "18:00:00"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/shifts/1/report_time" with body:
      """
      { "clear": true }
      """
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"@string@",
        "@id":"@string@",
        "@type":"Shift",
        "id":1,
        "type":"drive",
        "startsAt":"@string@.isDateTime()",
        "endsAt":"@string@.isDateTime()",
        "slots":1,
        "breakMinutes":30,
        "comment":null,
        "requiredSkills":[],
        "waitlist":[],
        "assignments":[
          {
            "@id":"@string@",
            "@type":"ShiftAssignment",
            "user":"@*@",
            "createdAt":"@string@.isDateTime()",
            "adjustment":null
          }
        ]
      }
      """

  Scenario: Employee can not report time for someone else or on a shift they are not assigned to
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
        "startsAt": "2026-07-13T09:00:00",
        "endsAt": "2026-07-13T17:00:00",
        "users": ["/api/users/1"]
      }
      """
    Then the response status code should be 201
    Given the user "alice" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "alice" sends a "PUT" request to "/api/shifts/1/report_time" with body:
      """
      {
        "user": "/api/users/1",
        "startsAt": "2026-07-13T09:00:00",
        "endsAt": "2026-07-13T18:00:00"
      }
      """
    Then the response status code should be 403
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "alice" sends a "PUT" request to "/api/shifts/1/report_time" with body:
      """
      {
        "startsAt": "2026-07-13T09:00:00",
        "endsAt": "2026-07-13T18:00:00"
      }
      """
    Then the response status code should be 400

  Scenario: Dispatcher exports monthly payroll variables
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
        "startsAt": "2026-07-13T09:00:00",
        "endsAt": "2026-07-13T17:00:00",
        "breakMinutes": 30,
        "users": ["/api/users/1"]
      }
      """
    Then the response status code should be 201
    Given the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/shifts/1/report_time" with body:
      """
      {
        "startsAt": "2026-07-13T09:00:00",
        "endsAt": "2026-07-13T19:00:00",
        "breakMinutes": 30
      }
      """
    Then the response status code should be 200
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/holiday_requests" with body:
      """
      {
        "startDate": "2026-07-30",
        "endDate": "2026-08-03",
        "comment": "Long weekend"
      }
      """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/holiday_requests/1/approve" with body:
      """
      {}
      """
    Then the response status code should be 200
    When the user "bob" sends a "GET" request to "/api/payroll_export?month=2026-07&format=csv"
    Then the response status code should be 200
    And the response should contain "sarah,,7.5,9.5,2,2"
    When the user "bob" sends a "GET" request to "/api/payroll_export?month=2026-07&format=xlsx"
    Then the response status code should be 200
    When the user "bob" sends a "GET" request to "/api/payroll_export?month=2026-07&format=doc"
    Then the response status code should be 400

  Scenario: Courier can not export payroll variables
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When the user "sarah" sends a "GET" request to "/api/payroll_export?month=2026-07&format=csv"
    Then the response status code should be 403
