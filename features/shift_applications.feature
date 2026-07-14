Feature: Shift applications

  Scenario: Courier can not apply to a shift of an unpublished week
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
        "slots": 1,
        "users": []
      }
      """
    Then the response status code should be 201
    Given the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/shifts/1/apply" with body:
      """
      {}
      """
    Then the response status code should be 400

  Scenario: Courier applies to a published shift, first come first served with waitlist and promotion
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
        "slots": 1,
        "users": []
      }
      """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shifts/publish_week" with body:
      """
      {
        "week": "2026-06-29"
      }
      """
    Then the response status code should be 204
    Given the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/shifts/1/apply" with body:
      """
      {}
      """
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Shift",
        "@id":"@string@",
        "@type":"Shift",
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
        ],
        "waitlist":[],
        "@*@":"@*@"
      }
      """
    Given the user "alice" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "alice" sends a "PUT" request to "/api/shifts/1/apply" with body:
      """
      {}
      """
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Shift",
        "@id":"@string@",
        "@type":"Shift",
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
        ],
        "waitlist":[
          {
            "@id":"@string@",
            "@type":"ShiftWaitlistEntry",
            "user":{
              "@id":"/api/users/2",
              "@type":"User",
              "username":"alice"
            },
            "createdAt":"@string@.isDateTime()"
          }
        ],
        "@*@":"@*@"
      }
      """
    Given the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/shifts/1/unapply" with body:
      """
      {}
      """
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Shift",
        "@id":"@string@",
        "@type":"Shift",
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
        ],
        "waitlist":[],
        "@*@":"@*@"
      }
      """

  Scenario: Courier can not apply without the required skills
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
    And the user "bob" sends a "POST" request to "/api/skills" with body:
      """
      {
        "name": "cargo bike + trailer"
      }
      """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shifts" with body:
      """
      {
        "type": "drive",
        "startsAt": "2026-06-29T09:00:00",
        "endsAt": "2026-06-29T17:00:00",
        "slots": 1,
        "requiredSkills": ["/api/skills/1"],
        "users": []
      }
      """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shifts/publish_week" with body:
      """
      {
        "week": "2026-06-29"
      }
      """
    Then the response status code should be 204
    Given the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/shifts/1/apply" with body:
      """
      {}
      """
    Then the response status code should be 400
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/skills/1" with body:
      """
      {
        "users": ["/api/users/1"]
      }
      """
    Then the response status code should be 200
    Given the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/shifts/1/apply" with body:
      """
      {}
      """
    Then the response status code should be 200

  Scenario: Open shifts only include published weeks
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
        "slots": 1,
        "users": []
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
        "slots": 1,
        "users": []
      }
      """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shifts/publish_week" with body:
      """
      {
        "week": "2026-06-29"
      }
      """
    Then the response status code should be 204
    Given the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "GET" request to "/api/shifts/open?date[after]=2026-06-29&date[before]=2026-07-12"
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Shift",
        "@id":"/api/shifts/open",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/shifts/1",
            "@*@":"@*@"
          }
        ],
        "hydra:totalItems":1,
        "@*@":"@*@"
      }
      """

  Scenario: Courier can not publish a week
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/shifts/publish_week" with body:
      """
      {
        "week": "2026-06-29"
      }
      """
    Then the response status code should be 403
