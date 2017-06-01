Feature: Manage restaurants

  Scenario: Retrieve the restaurants list
    Given the database is empty
    And the fixtures file "restaurants.yml" is loaded
    When I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to "/api/restaurants"
    Then the response status code should be 200
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
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
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
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
          "geo":null,
          "streetAddress":null,
          "name":"Café Barjot"
        }
      ],
      "hydra:totalItems":1,
      "hydra:view":{
        "@id":"/api/restaurants?coordinate=48.853286%2C2.369116\u0026distance=1500",
        "@type":"hydra:PartialCollectionView"
      },
      "hydra:search":{
        "@type":"hydra:IriTemplate",
        "hydra:template":"/api/restaurants{?}",
        "hydra:variableRepresentation":"BasicRepresentation",
        "hydra:mapping":[

        ]
      }
    }
    """

  Scenario: Retrieve a restaurant
    Given the restaurants are loaded:
      | id | name    | streetAddress                          | latlng             |
      | 12 | Nodaiwa | 272, rue Saint Honoré 75001 Paris 1er  | 48.864577,2.333338 |
    When I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to "/api/restaurants/12"
    Then the response status code should be 200
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/Restaurant",
      "@id":"/api/restaurants/12",
      "@type":"http://schema.org/Restaurant",
      "products":[

      ],
      "servesCuisine":[

      ],
      "geo":{
        "latitude":48.864577,
        "longitude":2.333338
      },
      "streetAddress":"272, rue Saint Honoré 75001 Paris 1er",
      "name":"Nodaiwa"
    }
    """
