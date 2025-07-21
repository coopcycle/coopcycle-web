Feature: Zones

  Scenario: Not authorized to list zones without admin role
    Given the fixtures files are loaded:
      | zones.yml |
    And the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/zones"
    Then the response status code should be 403

  Scenario: Not authorized to retrieve zone without admin role
    Given the fixtures files are loaded:
      | zones.yml |
    And the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/zones/1"
    Then the response status code should be 403

  Scenario: List zones as admin
    Given the fixtures files are loaded:
      | zones.yml |
    And the user "admin" is loaded:
      | email    | admin@coopcycle.org |
      | password | 123456              |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/zones"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/Zone",
        "@id": "/api/zones",
        "@type": "hydra:Collection",
        "hydra:member": [
          {
            "@id": "/api/zones/@integer@",
            "@type": "Zone",
            "id": "@integer@.greaterThan(0)",
            "name": "@string@",
            "geoJSON": {
              "type": "Polygon",
              "coordinates": "@array@"
            }
          },
          "@array_previous_repeat@"
        ],
        "hydra:totalItems": 3
      }
      """

  Scenario: Retrieve specific zone as admin
    Given the fixtures files are loaded:
      | zones.yml |
    And the user "admin" is loaded:
      | email    | admin@coopcycle.org |
      | password | 123456              |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/zones/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/Zone",
        "@id": "/api/zones/1",
        "@type": "Zone",
        "id": 1,
        "name": "Paris Center Zone",
        "geoJSON": {
          "type": "Polygon",
          "coordinates": "@array@"
        }
      }
      """

  Scenario: List zones with pagination
    Given the fixtures files are loaded:
      | zones.yml |
    And the user "admin" is loaded:
      | email    | admin@coopcycle.org |
      | password | 123456              |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/zones?itemsPerPage=2"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/Zone",
        "@id": "/api/zones",
        "@type": "hydra:Collection",
        "hydra:member": [
          {
            "@id": "/api/zones/@integer@",
            "@type": "Zone",
            "id": "@integer@.greaterThan(0)",
            "name": "@string@",
            "geoJSON": {
              "type": "Polygon",
              "coordinates": "@array@"
            }
          },
          {
            "@id": "/api/zones/@integer@",
            "@type": "Zone",
            "id": "@integer@.greaterThan(0)",
            "name": "@string@",
            "geoJSON": {
              "type": "Polygon",
              "coordinates": "@array@"
            }
          }
        ],
        "hydra:totalItems": 3,
        "hydra:view": {
          "@id": "/api/zones?itemsPerPage=2&page=1",
          "@type": "hydra:PartialCollectionView",
          "hydra:first": "/api/zones?itemsPerPage=2&page=1",
          "hydra:last": "/api/zones?itemsPerPage=2&page=2",
          "hydra:next": "/api/zones?itemsPerPage=2&page=2"
        }
      }
      """

  Scenario: Retrieve non-existent zone returns 404
    Given the fixtures files are loaded:
      | zones.yml |
    And the user "admin" is loaded:
      | email    | admin@coopcycle.org |
      | password | 123456              |
    And the user "admin" has role "ROLE_ADMIN"
    And the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/zones/999"
    Then the response status code should be 404
