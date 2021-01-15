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
          "time":1527855030000
        }
      ]
      """
    Then the response status code should be 200
    And the Tile38 collection "coopcycle_test:fleet" should contain key "bob" with point "48.8678,2.3677283,1527855030"

  Scenario: Location update with timestamp as string
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
          "time":"2020-06-30T19:00:56.069Z"
        }
      ]
      """
    Then the response status code should be 200
    And the Tile38 collection "coopcycle_test:fleet" should contain key "bob" with point "48.8678,2.3677283,1593543656"
