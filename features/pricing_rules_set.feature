Feature: Pricing rules set

  Scenario: Delete pricing rule set fails if store then succeed if no store
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "DELETE" request to "/api/pricing_rule_sets/1"
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
      """
      {"error": "Unable to delete because stores are linked to this pricing. Please delete store(s) Acme."}
      """
    And the user "admin" sends a "DELETE" request to "/api/stores/1"
    And the user "admin" sends a "DELETE" request to "/api/pricing_rule_sets/1"
    Then the response status code should be 204

    Scenario: Delete pricing rule set fails if restaurant then succeed if no restaurant
        Given the fixtures files are loaded:
        | sylius_channels.yml |
        | restaurants.yml          |
        And the user "admin" is loaded:
        | email      | admin@coopcycle.org |
        | password   | 123456            |
        And the user "admin" has role "ROLE_ADMIN"
        And the user "admin" is authenticated
        When I add "Content-Type" header equal to "application/ld+json"
        And I add "Accept" header equal to "application/ld+json"
        And the user "admin" sends a "DELETE" request to "/api/pricing_rule_sets/1"
        Then print last response
        Then the response status code should be 400
        And the response should be in JSON
        And the JSON should match:
        """
        {"error": "Unable to delete because restaurants are linked to this pricing. Please delete restaurant(s) Good Old Times with variables pricing."}
        """
        And the user "admin" sends a "DELETE" request to "/api/restaurants/7"
        And the user "admin" sends a "DELETE" request to "/api/pricing_rule_sets/1"
        Then the response status code should be 204
