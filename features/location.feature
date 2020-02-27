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
    And the Tile38 collection "coopcycle_test:fleet" should contain key "bob" with point "48.8678,2.3677283"

  Scenario: Location update with ETA calculations
    Given the current time is "2020-02-27 10:00:00"
    And the fixtures files are loaded:
      | location.yml        |
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
    And the Tile38 collection "coopcycle_test:fleet" should contain key "bob" with point "48.8678,2.3677283"
    And the Redis key "task:1:eta" should contain "2020-02-27T10:14:54+01:00"
    And the Redis key "task:2:eta" should contain "2020-02-27T10:33:20+01:00"
    And the Redis key "task:3:eta" should contain "2020-02-27T10:55:24+01:00"
