Feature: Skills

  Scenario: Dispatcher creates a skill and assigns trained users
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
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Skill",
        "@id":"/api/skills/1",
        "@type":"Skill",
        "id":1,
        "name":"cargo bike + trailer",
        "users":[]
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/skills/1" with body:
      """
      {
        "users": ["/api/users/1"]
      }
      """
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Skill",
        "@id":"/api/skills/1",
        "@type":"Skill",
        "id":1,
        "name":"cargo bike + trailer",
        "users":["/api/users/1"]
      }
      """

  Scenario: A user exposes their skills
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
        "name": "cargo bike + trailer",
        "users": ["/api/users/1"]
      }
      """
    Then the response status code should be 201
    When I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/users/1"
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"@string@",
        "@id":"/api/users/1",
        "@type":"User",
        "username":"sarah",
        "skills":[
          {
            "@id":"/api/skills/1",
            "@type":"Skill",
            "id":1,
            "name":"cargo bike + trailer"
          }
        ],
        "@*@":"@*@"
      }
      """

  Scenario: Dispatcher creates a shift requiring a skill
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
        "users": ["/api/users/1"]
      }
      """
    Then the response status code should be 201
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
        "requiredSkills":[
          {
            "@id":"/api/skills/1",
            "@type":"Skill",
            "id":1,
            "name":"cargo bike + trailer"
          }
        ],
        "assignments":"@array@"
      }
      """

  Scenario: Courier can not manage skills
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "GET" request to "/api/skills"
    Then the response status code should be 403
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/skills" with body:
      """
      {
        "name": "cargo bike + trailer"
      }
      """
    Then the response status code should be 403
