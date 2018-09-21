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
      "state": "normal",
      "address":{
        "@id":"@string@.startsWith('/api/addresses')",
        "@type":"http://schema.org/Place",
        "geo":{
          "latitude":@double@,
          "longitude":@double@
        },
        "streetAddress":"272, rue Saint Honoré 75001 Paris 1er",
        "name":null,
        "telephone": null
      },
      "hasMenu":{
        "@type":"Menu",
        "identifier":@string@,
        "hasMenuSection":[
          {
            "name":"Pizzas",
            "hasMenuItem":[
              {
                "@type":"MenuItem",
                "name":"Pizza",
                "description":null,
                "identifier":"PIZZA",
                "offers": {
                  "@type":"Offer",
                  "price":@integer@
                },
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
                "identifier":"HAMBURGER",
                "offers": {
                  "@type":"Offer",
                  "price":@integer@
                }
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

  Scenario: Change restaurant state
    Given the database is empty
    And the fixtures file "restaurants.yml" is loaded
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_RESTAURANT"
    And the restaurant with id "1" belongs to user "bob"
    And the user "bob" is authenticated
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/restaurants/1" with body:
      """
      {
        "state": "rush"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON

  Scenario: User has not sufficient access rights
    Given the database is empty
    And the fixtures file "restaurants.yml" is loaded
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/restaurants/1" with body:
      """
      {
        "state": "rush"
      }
      """
    Then the response status code should be 403

  Scenario: User is not authorized to modify restaurant
    Given the database is empty
    And the fixtures file "restaurants.yml" is loaded
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_RESTAURANT"
    And the restaurant with id "2" belongs to user "bob"
    And the user "bob" is authenticated
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/restaurants/1" with body:
      """
      {
        "state": "rush"
      }
      """
    Then the response status code should be 403

  Scenario: Retrieve restaurant products
    Given the database is empty
    And the fixtures file "sylius_locales.yml" is loaded
    And the fixtures file "products.yml" is loaded
    And the fixtures file "restaurants.yml" is loaded
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When I send a "GET" request to "/api/restaurants/1/products"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Product",
        "@id":"/api/restaurants/1/products",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"@string@.startsWith('/api/products')",
            "@type":"Product",
            "id":@integer@,
            "code":@string@,
            "name":@string@,
            "enabled":@boolean@
          },
          {
            "@id":"@string@.startsWith('/api/products')",
            "@type":"Product",
            "id":@integer@,
            "code":@string@,
            "name":@string@,
            "enabled":@boolean@
          }
        ],
        "hydra:totalItems":2
      }
      """
