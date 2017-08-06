Feature: Manage restaurants

  Scenario: Retrieve the restaurants list
    Given the database is empty
    And the fixtures file "restaurants.yml" is loaded
    When I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to "/api/restaurants"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/Restaurant",
      "@id":"/api/restaurants",
      "@type":"hydra:Collection",
      "hydra:member":@array@,
      "hydra:totalItems":3,
      "hydra:search":{
        "@type":"hydra:IriTemplate",
        "hydra:template":"/api/restaurants{?}",
        "hydra:variableRepresentation":"BasicRepresentation",
        "hydra:mapping":@array@
      }
    }
    """

  Scenario: Search restaurants
    Given the database is empty
    And the fixtures file "restaurants.yml" is loaded
    When I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to "/api/restaurants?coordinate=48.853286,2.369116&distance=1500"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/Restaurant",
      "@id":"/api/restaurants",
      "@type":"hydra:Collection",
      "hydra:member":[
        {
          "@id":"/api/restaurants/2",
          "@type":"http://schema.org/Restaurant",
          "products":@array@,
          "servesCuisine":@array@,
          "name":"Café Barjot",
          "hasMenu":@...@
        }
      ],
      "hydra:totalItems":1,
      "hydra:view":@...@,
      "hydra:search":@...@
    }
    """

  Scenario: Retrieve a restaurant
    Given the database is empty
    And the fixtures file "restaurants.yml" is loaded
    When I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to "/api/restaurants/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/Restaurant",
      "@id":"/api/restaurants/1",
      "@type":"http://schema.org/Restaurant",
      "products":@array@,
      "servesCuisine":@array@,
      "name":"Nodaiwa",
      "hasMenu":{
        "@id":"@string@.startsWith('/api/menus')",
        "@type":"http://schema.org/Menu",
        "hasMenuSection":[
          {
            "@id":"@string@.startsWith('/api/menu_sections')",
            "@type":"http://schema.org/MenuSection",
            "hasMenuItem":@array@,
            "description":null,
            "name":@string@
          },
          {
            "@id":"@string@.startsWith('/api/menu_sections')",
            "@type":"http://schema.org/MenuSection",
            "hasMenuItem":@array@,
            "description":null,
            "name":@string@
          }
        ],
        "description":null,
        "name":"Menu"
      }
    }
    """
