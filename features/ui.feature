Feature: User interface

  Scenario: Update homepage blocks
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_ADMIN"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/ui/homepage/blocks" with body:
      """
      {
        "blocks": [
          {
            "type": "exclusive"
          },
          {
            "type": "shop_collection",
            "data": {
              "slug": "foo"
            }
          }
        ]
      }
      """
    Then the response status code should be 200
    Given the user "bob" sends a "GET" request to "/api/ui/homepage/blocks"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
     """
     {
        "@context": "/api/contexts/Homepage",
        "@id": "/api/ui/homepage/blocks",
        "@type": "hydra:Collection",
        "hydra:totalItems": 2,
        "hydra:member": [
            {
                "@id": "/api/ui/homepage/blocks/1",
                "@type": "Homepage",
                "type": "exclusive",
                "data": []
            },
            {
                "@id": "/api/ui/homepage/blocks/2",
                "@type": "Homepage",
                "type": "shop_collection",
                "data": {
                    "slug": "foo"
                }
            }
        ]
    }
    """

