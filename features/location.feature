Feature: Location tracking

  Scenario: Location update
    Given the user is loaded:
      | email    | bob@coopcycle.org |
      | username | bob               |
      | password | 123456            |
    And the user "bob" is authenticated
    When I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/me/location" with body:
      """
      [
        {
          "latitude":48.8678,
          "longitude":2.3677283,
          "time":1527855030
        }
      ]
      """
    Then the response status code should be 200
