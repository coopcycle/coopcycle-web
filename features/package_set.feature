Feature: Package set
  Scenario: Get the applications of a package set
    Given the fixtures files are loaded:
    | sylius_channels.yml |
    | packages.yml |
    And the user "admin" is loaded:
    | email      | admin@coopcycle.org |
    | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/package_sets/1/applications"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
    """
      {
          "@context": "/api/contexts/PackageSet",
          "@id": "/api/package_sets",
          "@type": "hydra:Collection",
          "hydra:member": [
              {
                "entity": "AppBundle\\Entity\\Store",
                "name": "Acme",
                "id": @integer@
              },
              {
                "entity": "AppBundle\\Entity\\DeliveryForm",
                "name": @string@,
                "id": @integer@
             }
          ],
          "hydra:totalItems": 2
      }
    """

  Scenario: Delete a package set (of which a package has been linked to a task)
    Given the fixtures files are loaded:
    | sylius_channels.yml |
    | dispatch.yml |
    And the user "admin" is loaded:
    | email      | admin@coopcycle.org |
    | password   | 123456            |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "DELETE" request to "/api/package_sets/2"
    Then the response status code should be 204
