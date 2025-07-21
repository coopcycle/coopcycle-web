Feature: Pricing rules set

  Scenario: Delete pricing rule set fails if store then succeed if store is deleted
    Given the fixtures files are loaded:
      | sylius_products.yml |
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
    {
      "@context":"/api/contexts/ConstraintViolationList",
      "@type":"ConstraintViolationList",
      "hydra:title":"An error occurred",
      "hydra:description":"AppBundle\\Entity\\Delivery\\PricingRuleSet is used by AppBundle\\Entity\\Store#1",
      "violations":[
        {
          "propertyPath":"",
          "message":"AppBundle\\Entity\\Delivery\\PricingRuleSet is used by AppBundle\\Entity\\Store#1",
          "code":null
        }
      ]
    }
    """
    And the user "admin" sends a "DELETE" request to "/api/stores/1"
    And the user "admin" sends a "DELETE" request to "/api/pricing_rule_sets/1"
    Then the response status code should be 204

  Scenario: Delete pricing rule set fails if restaurant then succeed if restaurant is deleted
        Given the fixtures files are loaded:
        | sylius_products.yml   |
        | products.yml          |
        | restaurants.yml       |
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
        {
          "@context":"/api/contexts/ConstraintViolationList",
          "@type":"ConstraintViolationList",
          "hydra:title":"An error occurred",
          "hydra:description":"AppBundle\\Entity\\Delivery\\PricingRuleSet is used by AppBundle\\Entity\\Contract#4",
          "violations":[
            {
              "propertyPath":"",
              "message":"AppBundle\\Entity\\Delivery\\PricingRuleSet is used by AppBundle\\Entity\\Contract#4",
              "code":null
            }
          ]
        }
        """
        And the user "admin" sends a "DELETE" request to "/api/restaurants/7"
        And the user "admin" sends a "DELETE" request to "/api/pricing_rule_sets/1"
        Then the response status code should be 204

  Scenario: Get the applications of a pricing rule set
    Given the fixtures files are loaded:
      | sylius_products.yml  |
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
          "@id": "/api/pricing_rule_sets/1/applications",
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

  Scenario: Create pricing rule set with rules without names
    Given the fixtures files are loaded:
      | sylius_products.yml  |
    Given the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/pricing_rule_sets" with body:
      """
      {
        "name": "No Names Pricing Set",
        "strategy": "find",
        "rules": [
          {
            "expression": "distance > 0",
            "price": "500",
            "position": 1
          }
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
    """
      {
        "@context": "/api/contexts/PricingRuleSet",
        "@id": "@string@",
        "@type": "PricingRuleSet",
        "id": "@integer@",
        "name": "No Names Pricing Set",
        "strategy": "find",
        "options": [],
        "rules": [
          {
            "@id": "@string@",
            "@type": "PricingRule",
            "id": "@integer@",
            "target": "DELIVERY",
            "expression": "distance > 0",
            "price": "500",
            "position": 1,
            "name": null,
            "expressionAst": "@*@",
            "priceAst": "@*@"
          }
        ]
      }
    """

  Scenario: Create pricing rule set with rules containing names
    Given the fixtures files are loaded:
      | sylius_products.yml  |
    Given the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/pricing_rule_sets" with body:
      """
      {
        "name": "Test Pricing Set",
        "strategy": "find",
        "rules": [
          {
            "expression": "distance > 0",
            "price": "500",
            "position": 1,
            "name": "Base Delivery Fee"
          },
          {
            "expression": "weight > 1000",
            "price": "200",
            "position": 2,
            "name": "Heavy Package Surcharge"
          }
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
    """
      {
        "@context": "/api/contexts/PricingRuleSet",
        "@id": "@string@",
        "@type": "PricingRuleSet",
        "id": "@integer@",
        "name": "Test Pricing Set",
        "strategy": "find",
        "options": [],
        "rules": [
          {
            "@id": "@string@",
            "@type": "PricingRule",
            "id": "@integer@",
            "target": "DELIVERY",
            "expression": "distance > 0",
            "price": "500",
            "position": 1,
            "name": "Base Delivery Fee",
            "expressionAst": "@*@",
            "priceAst": "@*@"
          },
          {
            "@id": "@string@",
            "@type": "PricingRule",
            "id": "@integer@",
            "target": "DELIVERY",
            "expression": "weight > 1000",
            "price": "200",
            "position": 2,
            "name": "Heavy Package Surcharge",
            "expressionAst": "@*@",
            "priceAst": "@*@"
          }
        ]
      }
    """

  Scenario: Create pricing rule set with mixed rules (some with names, some without)
    Given the fixtures files are loaded:
      | sylius_products.yml  |
    Given the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/pricing_rule_sets" with body:
      """
      {
        "name": "Mixed Pricing Set",
        "strategy": "find",
        "rules": [
          {
            "expression": "distance > 0",
            "price": "500",
            "position": 1,
            "name": "Named Rule"
          },
          {
            "expression": "weight > 1000",
            "price": "200",
            "position": 2
          }
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
    """
      {
        "@context": "/api/contexts/PricingRuleSet",
        "@id": "@string@",
        "@type": "PricingRuleSet",
        "id": "@integer@",
        "name": "Mixed Pricing Set",
        "strategy": "find",
        "options": [],
        "rules": [
          {
            "@id": "@string@",
            "@type": "PricingRule",
            "id": "@integer@",
            "target": "DELIVERY",
            "expression": "distance > 0",
            "price": "500",
            "position": 1,
            "name": "Named Rule",
            "expressionAst": "@*@",
            "priceAst": "@*@"
          },
          {
            "@id": "@string@",
            "@type": "PricingRule",
            "id": "@integer@",
            "target": "DELIVERY",
            "expression": "weight > 1000",
            "price": "200",
            "position": 2,
            "name": null,
            "expressionAst": "@*@",
            "priceAst": "@*@"
          }
        ]
      }
    """

  Scenario: Create pricing rule set with empty name should not create ProductOption
    Given the fixtures files are loaded:
      | sylius_products.yml  |
    Given the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "POST" request to "/api/pricing_rule_sets" with body:
      """
      {
        "name": "Empty Name Test",
        "strategy": "find",
        "rules": [
          {
            "expression": "distance > 0",
            "price": "500",
            "position": 1,
            "name": ""
          },
          {
            "expression": "weight > 1000",
            "price": "200",
            "position": 2,
            "name": "   "
          }
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
    """
      {
        "@context": "/api/contexts/PricingRuleSet",
        "@id": "@string@",
        "@type": "PricingRuleSet",
        "id": "@integer@",
        "name": "Empty Name Test",
        "strategy": "find",
        "options": [],
        "rules": [
          {
            "@id": "@string@",
            "@type": "PricingRule",
            "id": "@integer@",
            "target": "DELIVERY",
            "expression": "distance > 0",
            "price": "500",
            "position": 1,
            "name": null,
            "expressionAst": "@*@",
            "priceAst": "@*@"
          },
          {
            "@id": "@string@",
            "@type": "PricingRule",
            "id": "@integer@",
            "target": "DELIVERY",
            "expression": "weight > 1000",
            "price": "200",
            "position": 2,
            "name": null,
            "expressionAst": "@*@",
            "priceAst": "@*@"
          }
        ]
      }
    """

  Scenario: Remove a rule from a pricing rule set
    Given the fixtures files are loaded:
      | sylius_products.yml             |
      | pricing_rule_set_with_names.yml |
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "PUT" request to "/api/pricing_rule_sets/1" with body:
      """
      {
        "name": "Updated Set with ProductOptions",
        "strategy": "find",
        "rules": [
          {
            "@id": "/api/pricing_rules/1",
            "expression": "distance > 0",
            "price": "500",
            "position": 1,
            "name": "Updated Existing Option Name"
          }
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
    """
      {
        "@context": "/api/contexts/PricingRuleSet",
        "@id": "/api/pricing_rule_sets/1",
        "@type": "PricingRuleSet",
        "id": 1,
        "name": "Updated Set with ProductOptions",
        "strategy": "find",
        "options": [],
        "rules": [
          {
            "@id": "/api/pricing_rules/1",
            "@type": "PricingRule",
            "id": 1,
            "target": "DELIVERY",
            "expression": "distance > 0",
            "price": "500",
            "position": 1,
            "name": "Updated Existing Option Name",
            "expressionAst": "@*@",
            "priceAst": "@*@"
          }
        ]
      }
    """

  Scenario: Add a rule to a pricing rule set
    Given the fixtures files are loaded:
      | sylius_products.yml             |
      | pricing_rule_set_with_names.yml |
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "PUT" request to "/api/pricing_rule_sets/1" with body:
      """
      {
        "name": "Updated Set with ProductOptions",
        "strategy": "find",
        "rules": [
          {
            "@id": "/api/pricing_rules/1",
            "expression": "distance > 0",
            "price": "500",
            "position": 1,
            "name": "Updated Existing Option Name"
          },
          {
            "@id": "/api/pricing_rules/2",
            "expression": "weight > 1000",
            "price": "200",
            "position": 2,
            "name": "New Option for Second Rule"
          },
          {
            "expression": "weight > 3000",
            "price": "300",
            "position": 3,
            "name": "New Rule"
          }
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
    """
      {
        "@context": "/api/contexts/PricingRuleSet",
        "@id": "/api/pricing_rule_sets/1",
        "@type": "PricingRuleSet",
        "id": 1,
        "name": "Updated Set with ProductOptions",
        "strategy": "find",
        "options": [],
        "rules": [
          {
            "@id": "/api/pricing_rules/1",
            "@type": "PricingRule",
            "id": 1,
            "target": "DELIVERY",
            "expression": "distance > 0",
            "price": "500",
            "position": 1,
            "name": "Updated Existing Option Name",
            "expressionAst": "@*@",
            "priceAst": "@*@"
          },
          {
            "@id": "/api/pricing_rules/2",
            "@type": "PricingRule",
            "id": 2,
            "target": "DELIVERY",
            "expression": "weight > 1000",
            "price": "200",
            "position": 2,
            "name": "New Option for Second Rule",
            "expressionAst": "@*@",
            "priceAst": "@*@"
          },
          {
            "@id": "/api/pricing_rules/3",
            "@type": "PricingRule",
            "id": 3,
            "target": "DELIVERY",
            "expression": "weight > 3000",
            "price": "300",
            "position": 3,
            "name": "New Rule",
            "expressionAst": "@*@",
            "priceAst": "@*@"
          }
        ]
      }
    """

  Scenario: Update pricing rule set should update the rules names
    Given the fixtures files are loaded:
      | sylius_products.yml             |
      | pricing_rule_set_with_names.yml |
    And the user "admin" is loaded:
      | email      | admin@coopcycle.org |
      | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "PUT" request to "/api/pricing_rule_sets/1" with body:
      """
      {
        "name": "Updated Set with ProductOptions",
        "strategy": "find",
        "rules": [
          {
            "@id": "/api/pricing_rules/1",
            "expression": "distance > 0",
            "price": "500",
            "position": 1,
            "name": "Updated Existing Option Name"
          },
          {
            "@id": "/api/pricing_rules/2",
            "expression": "weight > 1000",
            "price": "200",
            "position": 2,
            "name": "New Option for Second Rule"
          }
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
    """
      {
        "@context": "/api/contexts/PricingRuleSet",
        "@id": "/api/pricing_rule_sets/1",
        "@type": "PricingRuleSet",
        "id": 1,
        "name": "Updated Set with ProductOptions",
        "strategy": "find",
        "options": [],
        "rules": [
          {
            "@id": "/api/pricing_rules/1",
            "@type": "PricingRule",
            "id": 1,
            "target": "DELIVERY",
            "expression": "distance > 0",
            "price": "500",
            "position": 1,
            "name": "Updated Existing Option Name",
            "expressionAst": "@*@",
            "priceAst": "@*@"
          },
          {
            "@id": "/api/pricing_rules/2",
            "@type": "PricingRule",
            "id": 2,
            "target": "DELIVERY",
            "expression": "weight > 1000",
            "price": "200",
            "position": 2,
            "name": "New Option for Second Rule",
            "expressionAst": "@*@",
            "priceAst": "@*@"
          }
        ]
      }
    """
