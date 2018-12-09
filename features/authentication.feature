Feature: Authenticate

  Scenario: Login success
    Given the user is loaded:
      | email    | bob@coopcycle.org |
      | username | bob               |
      | password | 123456            |
    When I add "Accept" header equal to "application/ld+json"
    And I send a "POST" request to "/api/login_check" with parameters:
      | key       | value  |
      | _username | bob    |
      | _password | 123456 |
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "token": @string@,
        "roles": @array@,
        "username": "bob",
        "email": "bob@coopcycle.org",
        "id": @integer@,
        "refresh_token": @string@
      }
      """

  Scenario: Login by email success
    And the user is loaded:
      | email    | bob@coopcycle.org |
      | username | bob               |
      | password | 123456            |
    When I add "Accept" header equal to "application/ld+json"
    And I send a "POST" request to "/api/login_check" with parameters:
      | key       | value  |
      | _username | bob@coopcycle.org    |
      | _password | 123456 |
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "token": @string@,
      "roles": @array@,
      "username": "bob",
      "email": "bob@coopcycle.org",
      "id": @integer@,
      "refresh_token": @string@
    }
    """

  Scenario: Login failure
    When I add "Accept" header equal to "application/ld+json"
    And I send a "POST" request to "/api/login_check" with parameters:
      | key       | value  |
      | _username | nope   |
      | _password | 123456 |
    Then the response status code should be 401
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "code": 401,
      "message": @string@
    }
    """

  Scenario: Authenticated request
    Given the user is loaded:
      | email    | bob@coopcycle.org |
      | username | bob               |
      | password | 123456            |
    And the user "bob" is authenticated
    When I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/me"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "@context": "/api/contexts/ApiUser",
      "@id": "/api/api_users/1",
      "@type": "ApiUser",
      "addresses": [],
      "username": "bob",
      "email": "bob@coopcycle.org",
      "roles":["ROLE_USER"]
    }
    """
