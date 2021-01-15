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
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/me/remote_push_tokens" with body:
      """
      {
        "platform": "ios",
        "token": "abcdefghi"
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

  Scenario: Delete ios token
    Given the user is loaded:
      | email    | bob@coopcycle.org |
      | username | bob               |
      | password | 123456            |
    And the user "bob" is authenticated
    And the user "bob" has a remote push token with value "1234567890" for platform "ios"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "DELETE" request to "/api/me/remote_push_tokens/1234567890"
    Then the response status code should be 204

  Scenario: Delete Android token
    Given the user is loaded:
      | email    | bob@coopcycle.org |
      | username | bob               |
      | password | 123456            |
    And the user "bob" is authenticated
    And the user "bob" has a remote push token with value "abc123:abc123-abc123_abc123-abc123_abc123_abc123_abc123" for platform "android"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "DELETE" request to "/api/me/remote_push_tokens/abc123:abc123-abc123_abc123-abc123_abc123_abc123_abc123"
    Then the response status code should be 204

  Scenario: Token not found
    Given the user is loaded:
      | email    | bob@coopcycle.org |
      | username | bob               |
      | password | 12345678          |
    Given the user is loaded:
      | email    | sarah@coopcycle.org |
      | username | sarah               |
      | password | 12345678            |
    And the user "bob" is authenticated
    And the user "sarah" has a remote push token with value "1234567890" for platform "android"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "DELETE" request to "/api/me/remote_push_tokens/1234567890"
    Then the response status code should be 404
