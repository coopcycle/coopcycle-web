Feature: User interface

  Scenario: Update homepage blocks
    Given the fixtures files are loaded:
      | ui.yml |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_ADMIN"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/ui/homepage" with body:
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
    Then print last response
    Then the response status code should be 200
