Feature: Manage restaurants

  Scenario: Retrieve the restaurants list
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | restaurants.yml     |
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
      "hydra:totalItems":3
    }
    """

  Scenario: Search restaurants
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | restaurants.yml     |
    When I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to "/api/restaurants?coordinate=48.853286,2.369116"
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
          "name":"Café Barjot"
        }
      ],
      "hydra:totalItems":1,
      "hydra:view":@...@,
      "hydra:search":@...@
    }
    """

  Scenario: Retrieve a restaurant
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_locales.yml  |
      | products.yml        |
      | restaurants.yml     |
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
      "telephone": null,
      "image":@string@,
      "hasMenu":"@string@.startsWith('/api/restaurants/menus')",
      "openingHoursSpecification":[
        {
          "@type":"OpeningHoursSpecification",
          "opens":"11:30",
          "closes":"14:30",
          "dayOfWeek":["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"]
        }
      ],
      "specialOpeningHoursSpecification":[],
      "availabilities":@array@,
      "minimumCartAmount":@integer@,
      "flatDeliveryPrice":@integer@
    }
    """

  Scenario: Retrieve a restaurant's menu
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_locales.yml  |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the restaurant with id "1" has menu:
      | section | product   |
      | Pizzas  | PIZZA     |
      | Burgers | HAMBURGER |
    When I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to "/api/restaurants/1/menu"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/Menu",
      "@id":"@string@.startsWith('/api/restaurants/menus')",
      "@type":"http://schema.org/Menu",
      "name":@string@,
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
              "enabled":@boolean@,
              "offers": {
                "@type":"Offer",
                "price":@integer@
              },
              "menuAddOn":[
                {
                  "@type":"MenuSection",
                  "name":"Pizza topping",
                  "identifier":"PIZZA_TOPPING",
                  "additionalType":"free",
                  "additional":false,
                  "hasMenuItem":[
                    {
                      "@type":"MenuItem",
                      "name":"Extra cheese",
                      "identifier":"PIZZA_TOPPING_EXTRA_CHEESE",
                      "offers":{
                        "@type":"Offer",
                        "price":0
                      }
                    },
                    {
                      "@type":"MenuItem",
                      "name":"Pepperoni",
                      "identifier":"PIZZA_TOPPING_PEPPERONI",
                      "offers":{
                        "@type":"Offer",
                        "price":0
                      }
                    }
                  ]
                }
              ]
            }
          ]
        },
        {
          "name":"Burgers",
          "hasMenuItem":[
            {
              "@type":"MenuItem",
              "name":"Hamburger",
              "description":null,
              "identifier":"HAMBURGER",
              "enabled":@boolean@,
              "offers": {
                "@type":"Offer",
                "price":@integer@
              }
            }
          ]
        }
      ]
    }
    """

  Scenario: Retrieve all menus for a restaurant
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_locales.yml  |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the restaurant with id "1" has menu:
      | section | product   |
      | Pizzas  | PIZZA     |
      | Burgers | HAMBURGER |
    And the restaurant with id "1" has menu:
      | section  | product |
      | Desserts | CAKE    |
    When I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to "/api/restaurants/1/menus"
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
          "@id":"@string@.startsWith('/api/restaurants/menus')",
          "@type":"http://schema.org/Menu",
          "name":"Menu",
          "identifier":@string@,
          "hasMenuSection":@array@
        },
        {
          "@id":"@string@.startsWith('/api/restaurants/menus')",
          "@type":"http://schema.org/Menu",
          "name":"Menu",
          "identifier":@string@,
          "hasMenuSection":@array@
        }
      ],
      "hydra:totalItems":2
    }
    """

  Scenario: Restaurant is deliverable
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | restaurants.yml     |
    When I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to "/api/restaurants/1/can-deliver/48.855799,2.359207"
    Then the response status code should be 200
    And the response should be in JSON

  Scenario: Restaurant is not deliverable
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | restaurants.yml     |
    When I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to "/api/restaurants/1/can-deliver/48.882305,2.365448"
    Then the response status code should be 400
    And the response should be in JSON

  Scenario: Change active menu
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_locales.yml  |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the restaurant with id "1" has menu:
      | section | product   |
      | Pizzas  | PIZZA     |
      | Burgers | HAMBURGER |
    And the restaurant with id "1" has menu:
      | section  | product |
      | Desserts | CAKE    |
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
        "hasMenu": "/api/restaurants/menus/2"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Restaurant",
        "@id":"@string@.startsWith('/api/restaurants')",
        "@type":"http://schema.org/Restaurant",
        "id":@integer@,
        "name":@string@,
        "servesCuisine":@array@,
        "enabled":true,
        "address":@...@,
        "state":"rush",
        "telephone":null,
        "openingHoursSpecification":@array@,
        "specialOpeningHoursSpecification":@array@,
        "hasMenu":"/api/restaurants/menus/2",
        "availabilities":@array@,
        "minimumCartAmount":@integer@,
        "flatDeliveryPrice":@integer@,
        "image":@string@
      }
      """

  Scenario: User has not sufficient access rights
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | restaurants.yml     |
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
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | restaurants.yml     |
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

  Scenario: Change restaurant state
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | restaurants.yml     |
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
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Restaurant",
        "@id":"/api/restaurants/1",
        "@type":"http://schema.org/Restaurant",
        "id":1,
        "name":"Nodaiwa",
        "servesCuisine":@array@,
        "enabled":true,
        "address":@...@,
        "state":"rush",
        "telephone":null,
        "openingHoursSpecification":@array@,
        "specialOpeningHoursSpecification":@array@,
        "availabilities":@array@,
        "minimumCartAmount":@integer@,
        "flatDeliveryPrice":@integer@,
        "image":@string@
      }
      """

  Scenario: Retrieve restaurant products
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_locales.yml  |
      | products.yml        |
      | restaurants.yml     |
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
            "description":null,
            "enabled":@boolean@
          },
          {
            "@id":"@string@.startsWith('/api/products')",
            "@type":"Product",
            "id":@integer@,
            "code":@string@,
            "name":@string@,
            "description":null,
            "enabled":@boolean@
          }
        ],
        "hydra:totalItems":2
      }
      """

  Scenario: Deleted products are not retrieved
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_locales.yml  |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the product with code "PIZZA" is soft deleted
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
            "description":null,
            "enabled":@boolean@
          }
        ],
        "hydra:totalItems":1
      }
      """
