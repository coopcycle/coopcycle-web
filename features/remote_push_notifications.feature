Feature: Remote push notifications

  Scenario: Store iOS token
    Given the user is loaded:
      | email    | bob@coopcycle.org |
      | username | bob               |
      | password | 123456            |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/me/remote_push_tokens" with body:
      """
      {
        "platform": "ios",
        "token": "1234567890"
      }
      """
    Then the response status code should be 201
    And the response should be in JSON

  Scenario: Store Android token
    Given the user is loaded:
      | email    | bob@coopcycle.org |
      | username | bob               |
      | password | 123456            |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/me/remote_push_tokens" with body:
      """
      {
        "platform": "android",
        "token": "1234567890"
      }
      """
    Then the response status code should be 201
    And the response should be in JSON

