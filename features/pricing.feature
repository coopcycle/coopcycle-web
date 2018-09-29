Feature: Pricing

  Scenario: Missing mandatory parameter
    Given the fixtures file "stores.yml" is loaded
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the store with name "Acme" belongs to user "bob"
    And the store with name "Acme" is authenticated as "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/pricing/calculate-price"
    Then the response status code should be 400
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Error",
        "@type":"hydra:Error",
        "hydra:title":"An error occurred",
        "hydra:description":"Parameter dropoffAddress is mandatory",
        "trace":@array@
      }
      """

  Scenario: Get delivery price
    Given the fixtures file "stores.yml" is loaded
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the store with name "Acme" belongs to user "bob"
    And the store with name "Acme" is authenticated as "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/pricing/calculate-price?dropoffAddress=23+rue+de+Rivoli"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      499
      """
