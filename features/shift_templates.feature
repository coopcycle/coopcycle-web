Feature: Shift templates

  Scenario: Dispatcher saves a week as a template, then applies it without assignees
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
        "activity": "delivery",
        "startsAt": "2026-07-13T09:00:00",
        "endsAt": "2026-07-13T17:00:00",
        "slots": 1,
        "breakMinutes": 30,
        "comment": "Bring your bike lock",
        "users": ["/api/users/1"]
      }
      """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shifts" with body:
      """
      {
        "activity": "delivery",
        "startsAt": "2026-07-16T12:00:00",
        "endsAt": "2026-07-16T15:00:00",
        "slots": 2,
        "breakMinutes": 0
      }
      """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shift_templates" with body:
      """
      {
        "name": "Summer week",
        "week": "2026-07-13"
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ShiftTemplate",
        "@id":"@string@",
        "@type":"ShiftTemplate",
        "id":1,
        "name":"Summer week",
        "shifts":[
          {
            "@type":"ShiftTemplateShift",
            "@id":"@string@",
            "activity":"delivery",
            "dayOfWeek":1,
            "slots":1,
            "breakMinutes":30,
            "comment":"Bring your bike lock",
            "requiredSkills":[],
            "startTime":"09:00",
            "endTime":"17:00"
          },
          {
            "@type":"ShiftTemplateShift",
            "@id":"@string@",
            "activity":"delivery",
            "dayOfWeek":4,
            "slots":2,
            "breakMinutes":0,
            "comment":null,
            "requiredSkills":[],
            "startTime":"12:00",
            "endTime":"15:00"
          }
        ],
        "shiftCount":2,
        "hasAssignees":true
      }
      """
    When I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/shift_templates"
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ShiftTemplate",
        "@id":"/api/shift_templates",
        "@type":"hydra:Collection",
        "hydra:member":"@array@",
        "hydra:totalItems":1
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shift_templates/1/apply" with body:
      """
      {
        "targetWeek": "2026-08-03",
        "includeAssignees": false
      }
      """
    Then the response status code should be 201
    And the JSON should match:
      """
      {
        "@context":"@*@",
        "@id":"@string@",
        "@type":"ShiftTemplateApplyResult",
        "created":2
      }
      """
    When I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/shifts?date[after]=2026-08-03&date[before]=2026-08-09"
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Shift",
        "@id":"/api/shifts",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"@string@",
            "@type":"Shift",
            "id":"@integer@",
            "activity":"delivery",
            "startsAt":"2026-08-03T09:00:00+02:00",
            "endsAt":"2026-08-03T17:00:00+02:00",
            "slots":1,
            "breakMinutes":30,
            "comment":"Bring your bike lock",
            "requiredSkills":[],
            "waitlist":[],
            "assignments":[]
          },
          {
            "@id":"@string@",
            "@type":"Shift",
            "id":"@integer@",
            "activity":"delivery",
            "startsAt":"2026-08-06T12:00:00+02:00",
            "endsAt":"2026-08-06T15:00:00+02:00",
            "slots":2,
            "breakMinutes":0,
            "comment":null,
            "requiredSkills":[],
            "waitlist":[],
            "assignments":[]
          }
        ],
        "hydra:totalItems":2,
        "hydra:view":"@*@",
        "hydra:search":"@*@"
      }
      """

  Scenario: Dispatcher applies a template with assignees, skipping a user on approved holiday
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
        "activity": "delivery",
        "startsAt": "2026-07-13T09:00:00",
        "endsAt": "2026-07-13T17:00:00",
        "users": ["/api/users/1", "/api/users/2"]
      }
      """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shift_templates" with body:
      """
      { "name": "Crew week", "week": "2026-07-13" }
      """
    Then the response status code should be 201
    Given the user "alice" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "alice" sends a "POST" request to "/api/holiday_requests" with body:
      """
      {
        "startDate": "2026-08-03",
        "endDate": "2026-08-03",
        "comment": "Day off"
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
    And the user "bob" sends a "POST" request to "/api/shift_templates/1/apply" with body:
      """
      { "targetWeek": "2026-08-03", "includeAssignees": true }
      """
    Then the response status code should be 201
    And the response should contain "\"created\":1"
    When I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/shifts?date[after]=2026-08-03&date[before]=2026-08-09"
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Shift",
        "@id":"/api/shifts",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"@string@",
            "@type":"Shift",
            "id":"@integer@",
            "activity":"delivery",
            "startsAt":"2026-08-03T09:00:00+02:00",
            "endsAt":"2026-08-03T17:00:00+02:00",
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
                  "@id":"/api/users/1",
                  "@type":"User",
                  "username":"sarah"
                },
                "createdAt":"@string@.isDateTime()",
                "adjustment":null
              }
            ]
          }
        ],
        "hydra:totalItems":1,
        "hydra:view":"@*@",
        "hydra:search":"@*@"
      }
      """

  Scenario: Dispatcher deletes a template
    Given the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shifts" with body:
      """
      {
        "activity": "delivery",
        "startsAt": "2026-07-13T09:00:00",
        "endsAt": "2026-07-13T17:00:00"
      }
      """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shift_templates" with body:
      """
      { "name": "To delete", "week": "2026-07-13" }
      """
    Then the response status code should be 201
    When the user "bob" sends a "DELETE" request to "/api/shift_templates/1"
    Then the response status code should be 204
    When I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/shift_templates"
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ShiftTemplate",
        "@id":"/api/shift_templates",
        "@type":"hydra:Collection",
        "hydra:member":[],
        "hydra:totalItems":0
      }
      """

  Scenario: Courier can not manage shift templates
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "GET" request to "/api/shift_templates"
    Then the response status code should be 403
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/shift_templates" with body:
      """
      { "name": "Nope", "week": "2026-07-13" }
      """
    Then the response status code should be 403
