Feature: Pricing rules set

  Scenario: Delete pricing rule set fails if store then succeed if store is deleted
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
      {"error": [{"entity": "AppBundle\\Entity\\Store", "name": "Acme","id": 1}]}
      """
    And the user "admin" sends a "DELETE" request to "/api/stores/1"
    And the user "admin" sends a "DELETE" request to "/api/pricing_rule_sets/1"
    Then the response status code should be 204

    Scenario: Delete pricing rule set fails if restaurant then succeed if restaurant is deleted
        Given the fixtures files are loaded:
        | sylius_channels.yml |
        | products.yml          |
        | restaurants.yml          |
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
         {"error": [{"entity": "AppBundle\\Entity\\LocalBusiness", "name": "Good Old Times with variables pricing","id": 7}]}
        """
        And the user "admin" sends a "DELETE" request to "/api/restaurants/7"
        And the user "admin" sends a "DELETE" request to "/api/pricing_rule_sets/1"
        Then the response status code should be 204

  Scenario: Get the applications of a pricing rule set
    Given the fixtures files are loaded:
    | sylius_channels.yml |
    | pricing_rule_set.yml |
    And the user "admin" is loaded:
    | email      | admin@coopcycle.org |
    | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/pricing_rule_sets/1/applications"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
    """
      {
          "@context": "/api/contexts/PricingRuleSet",
          "@id": "/api/pricing_rule_sets",
          "@type": "hydra:Collection",
          "hydra:member": [
              {
                  "entity": "AppBundle\\Entity\\Store",
                  "name": "Acme",
                  "id": 1
              },
              {
                  "entity": "AppBundle\\Entity\\LocalBusiness",
                  "name": "Good Old Times with variables pricing",
                  "id": 1
              }
          ],
          "hydra:totalItems": 2
      }
    """
