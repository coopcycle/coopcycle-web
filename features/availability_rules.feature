Feature: Availability rules

  Scenario: Employee declares recurring availability and reads it back
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/availability_rules" with body:
      """
      {
        "type": "available",
        "dayOfWeek": 1,
        "startTime": "13:00",
        "endTime": "18:00",
        "comment": "Free on Monday afternoons"
      }
      """
    Then the response status code should be 201
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/AvailabilityRule",
        "@id":"/api/availability_rules/1",
        "@type":"AvailabilityRule",
        "id":1,
        "user":"/api/users/1",
        "type":"available",
        "dayOfWeek":1,
        "startTime":"13:00",
        "endTime":"18:00",
        "comment":"Free on Monday afternoons"
      }
      """
    When I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "GET" request to "/api/me/availability_rules"
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/AvailabilityRule",
        "@id":"/api/me/availability_rules",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/availability_rules/1",
            "@type":"AvailabilityRule",
            "id":1,
            "user":"/api/users/1",
            "type":"available",
            "dayOfWeek":1,
            "startTime":"13:00",
            "endTime":"18:00",
            "comment":"Free on Monday afternoons"
          }
        ],
        "hydra:totalItems":1,
        "hydra:search":"@*@"
      }
      """

  Scenario: Employee declares an unavailable slot and later deletes it
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/availability_rules" with body:
      """
      {
        "type": "unavailable",
        "dayOfWeek": 5,
        "startTime": "08:00",
        "endTime": "12:00"
      }
      """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "DELETE" request to "/api/availability_rules/1"
    Then the response status code should be 204
    When I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "GET" request to "/api/me/availability_rules"
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/AvailabilityRule",
        "@id":"/api/me/availability_rules",
        "@type":"hydra:Collection",
        "hydra:member":[],
        "hydra:totalItems":0,
        "hydra:search":"@*@"
      }
      """

  Scenario: An employee can not read, edit or delete another employee's rule
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the courier "alice" is loaded:
      | email    | alice@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/availability_rules" with body:
      """
      {
        "type": "available",
        "dayOfWeek": 1,
        "startTime": "13:00",
        "endTime": "18:00"
      }
      """
    Then the response status code should be 201
    Given the user "alice" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "alice" sends a "GET" request to "/api/availability_rules/1"
    Then the response status code should be 403
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "alice" sends a "PUT" request to "/api/availability_rules/1" with body:
      """
      {
        "type": "unavailable",
        "dayOfWeek": 2,
        "startTime": "09:00",
        "endTime": "10:00"
      }
      """
    Then the response status code should be 403
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "alice" sends a "DELETE" request to "/api/availability_rules/1"
    Then the response status code should be 403

  Scenario: Dispatcher declares an availability rule on behalf of an employee
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
    And the user "bob" sends a "POST" request to "/api/availability_rules" with body:
      """
      {
        "user": "/api/users/1",
        "type": "unavailable",
        "dayOfWeek": 3,
        "startTime": "14:00",
        "endTime": "17:00",
        "comment": "Second job"
      }
      """
    Then the response status code should be 201
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/AvailabilityRule",
        "@id":"/api/availability_rules/1",
        "@type":"AvailabilityRule",
        "id":1,
        "user":"/api/users/1",
        "type":"unavailable",
        "dayOfWeek":3,
        "startTime":"14:00",
        "endTime":"17:00",
        "comment":"Second job"
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/availability_rules/1" with body:
      """
      {
        "type": "unavailable",
        "dayOfWeek": 3,
        "startTime": "15:00",
        "endTime": "17:00",
        "comment": "Second job, changed"
      }
      """
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/AvailabilityRule",
        "@id":"/api/availability_rules/1",
        "@type":"AvailabilityRule",
        "id":1,
        "user":"/api/users/1",
        "type":"unavailable",
        "dayOfWeek":3,
        "startTime":"15:00",
        "endTime":"17:00",
        "comment":"Second job, changed"
      }
      """

  Scenario: A courier submitting "user" on create is ignored, not honored
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the courier "alice" is loaded:
      | email    | alice@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/availability_rules" with body:
      """
      {
        "user": "/api/users/2",
        "type": "available",
        "dayOfWeek": 1,
        "startTime": "13:00",
        "endTime": "18:00"
      }
      """
    Then the response status code should be 201
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/AvailabilityRule",
        "@id":"/api/availability_rules/1",
        "@type":"AvailabilityRule",
        "id":1,
        "user":"/api/users/1",
        "type":"available",
        "dayOfWeek":1,
        "startTime":"13:00",
        "endTime":"18:00",
        "comment":null
      }
      """

  Scenario: Dispatcher lists all availability rules, filterable by employee
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the courier "alice" is loaded:
      | email    | alice@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/availability_rules" with body:
      """
      {
        "type": "available",
        "dayOfWeek": 1,
        "startTime": "13:00",
        "endTime": "18:00"
      }
      """
    Then the response status code should be 201
    Given the user "alice" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "alice" sends a "POST" request to "/api/availability_rules" with body:
      """
      {
        "type": "unavailable",
        "dayOfWeek": 5,
        "startTime": "08:00",
        "endTime": "12:00"
      }
      """
    Then the response status code should be 201
    Given the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/availability_rules"
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/AvailabilityRule",
        "@id":"/api/availability_rules",
        "@type":"hydra:Collection",
        "hydra:member":"@array@",
        "hydra:totalItems":2,
        "hydra:search":"@*@"
      }
      """
    When I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/availability_rules?user=/api/users/1"
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/AvailabilityRule",
        "@id":"@string@",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/availability_rules/1",
            "@type":"AvailabilityRule",
            "user":"/api/users/1",
            "@*@":"@*@"
          }
        ],
        "hydra:totalItems":1,
        "hydra:search":"@*@",
        "hydra:view":"@*@"
      }
      """

  Scenario: Courier can not list all availability rules
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "GET" request to "/api/availability_rules"
    Then the response status code should be 403

  Scenario: An end time before the start time is rejected
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/availability_rules" with body:
      """
      {
        "type": "available",
        "dayOfWeek": 1,
        "startTime": "18:00",
        "endTime": "13:00"
      }
      """
    Then the response status code should be 400

  Scenario: An invalid time format is rejected
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/availability_rules" with body:
      """
      {
        "type": "available",
        "dayOfWeek": 1,
        "startTime": "9:00",
        "endTime": "13:00"
      }
      """
    Then the response status code should be 400
