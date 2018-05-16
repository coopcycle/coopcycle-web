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
        "hydra:template":"/api/restaurants{?coordinate,distance}",
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
          "id": 2,
          "@type":"http://schema.org/Restaurant",
          "servesCuisine":@array@,
          "enabled":true,
          "address":@...@,
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
    And the fixtures file "sylius_locales.yml" is loaded
    And the fixtures file "products.yml" is loaded
    And the fixtures file "restaurants.yml" is loaded
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the restaurant with id "1" has menu:
      | section | product   |
      | Pizzas  | PIZZA     |
      | Burger  | HAMBURGER |
    When I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to "/api/restaurants/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/Restaurant",
      "@id":"/api/restaurants/1",
      "id":1,
      "@type":"http://schema.org/Restaurant",
      "servesCuisine":@array@,
      "enabled":true,
      "name":"Nodaiwa",
      "address":{
        "@id":"@string@.startsWith('/api/addresses')",
        "@type":"http://schema.org/Place",
        "geo":{
          "latitude":@double@,
          "longitude":@double@
        },
        "streetAddress":"272, rue Saint Honoré 75001 Paris 1er",
        "name":null
      },
      "hasMenu":{
        "hasMenuSection":[
          {
            "name":"Pizzas",
            "hasMenuItem":[
              {
                "@type":"MenuItem",
                "name":"Pizza",
                "description":null,
                "identifier":"PIZZA",
                "menuAddOn":[
                  {
                    "@type":"MenuSection",
                    "name":"Pizza topping",
                    "identifier":"PIZZA_TOPPING",
                    "hasMenuItem":[
                      {
                        "@type":"MenuItem",
                        "name":"Extra cheese",
                        "identifier":"PIZZA_TOPPING_EXTRA_CHEESE"
                      },
                      {
                        "@type":"MenuItem",
                        "name":"Pepperoni",
                        "identifier":"PIZZA_TOPPING_PEPPERONI"
                      }
                    ]
                  }
                ]
              }
            ]
          },
          {
            "name":"Burger",
            "hasMenuItem":[
              {
                "@type":"MenuItem",
                "name":"Hamburger",
                "description":null,
                "identifier":"HAMBURGER"
              }
            ]
          }
        ]
      },
      "openingHours":@array@,
      "availabilities":@array@,
      "minimumCartAmount":@integer@,
      "flatDeliveryPrice":@integer@
    }
    """

  Scenario: Restaurant is deliverable
    Given the database is empty
    And the fixtures file "restaurants.yml" is loaded
    When I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to "/api/restaurants/1/can-deliver/48.855799,2.359207"
    Then the response status code should be 200
    And the response should be in JSON

  Scenario: Restaurant is not deliverable
    Given the database is empty
    And the fixtures file "restaurants.yml" is loaded
    When I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to "/api/restaurants/1/can-deliver/48.882305,2.365448"
    Then the response status code should be 400
    And the response should be in JSON
