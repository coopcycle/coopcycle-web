Feature: Addresses

  Scenario: Not authorized to list addresses
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/addresses"
    Then the response status code should be 403

  Scenario: Not authorized to retrieve address
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/addresses/1"
    Then the response status code should be 403

Scenario: Ability to PATCH an address as admin
  Given the fixtures files are loaded:
    | sylius_channels.yml |
    | stores.yml          |
  And the user "bob" is loaded:
    | email      | bob@coopcycle.org |
    | password   | 123456            |
  And the user "bob" has role "ROLE_ADMIN"
  And the user "bob" is authenticated
  When I add "Content-Type" header equal to "application/ld+json"
  And I add "Accept" header equal to "application/ld+json"
  And the user "bob" sends a "PATCH" request to "/api/addresses/1" with body:
  """
    {
      "streetAddress":"10 rue Mouton Duvernet, Paris",
      "name": "Pikachu"
    }
  """
  Then the response status code should be 200
  When I add "Content-Type" header equal to "application/ld+json"
  And I add "Accept" header equal to "application/ld+json"
  And the user "bob" sends a "GET" request to "/api/addresses/1"
  Then the response status code should be 200
  And the response should be in JSON
  Then print last JSON response
  And the JSON should match:
  """
    {
      "streetAddress":"10 rue Mouton Duvernet, Paris",
      "name": "Pikachu",
      "geo": {
        "@type": "GeoCoordinates",
        "latitude": 48.864577,
        "longitude": 2.333338
      },
      "@*@": "@*@"
    }
  """
