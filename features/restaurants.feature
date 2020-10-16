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
          "@type":"http://schema.org/Restaurant",
          "id":2,
          "name":"Café Barjot",
          "description":null,
          "enabled":true,
          "depositRefundEnabled":false,
          "depositRefundOptin":true,
          "telephone":"+33612345678",
          "address":{
            "@id":"/api/addresses/2",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":48.846656,
              "longitude":2.369052
            },
            "streetAddress":"18, avenue Ledru-Rollin 75012 Paris 12ème",
            "telephone":null,
            "name":null
          },
          "state":"normal",
          "openingHoursSpecification":[
            {
              "@type":"OpeningHoursSpecification",
              "opens":"11:30",
              "closes":"14:30",
              "dayOfWeek":[
                "Monday",
                "Tuesday",
                "Wednesday",
                "Thursday",
                "Friday",
                "Saturday"
              ]
            }
          ],
          "specialOpeningHoursSpecification":[],
          "image":@string@,
          "fulfillmentMethods":@array@
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
      "enabled":true,
      "depositRefundEnabled": false,
      "depositRefundOptin": true,
      "name":"Nodaiwa",
      "description": null,
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
      "telephone":"+33612345678",
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
      "fulfillmentMethods":@array@
    }
    """

  Scenario: Retrieve a restaurant timing (tomorrow)
    Given the current time is "2020-09-17 15:00:00"
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
    And I send a "GET" request to "/api/restaurants/1/timing"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
    """
    {
     "@context":"@*@",
     "@type":"Timing",
     "@id":@string@,
     "delivery":{
        "@context":"@*@",
        "@type":"TimeInfo",
        "@id":@string@,
        "range":[
          "2020-09-18T11:55:00+02:00",
          "2020-09-18T12:05:00+02:00"
        ],
        "today":false,
        "fast": false,
        "diff":"1255 - 1265"
      },
      "collection":null
    }
    """

  Scenario: Retrieve a restaurant timing (today)
    Given the current time is "2020-09-17 12:00:00"
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
    And I send a "GET" request to "/api/restaurants/1/timing"
    Then the response status code should be 200
    And the response should be in JSON
    And print last response
    And the JSON should match:
    """
    {
      "@context":"@*@",
      "@type":"Timing",
      "@id":@string@,
      "delivery":{
        "@context":"@*@",
        "@type":"TimeInfo",
        "@id":@string@,
        "range":[
          "2020-09-17T12:25:00+02:00",
          "2020-09-17T12:35:00+02:00"
        ],
        "today":true,
        "fast":true,
        "diff":"25 - 35"
      },
      "collection":null
    }
    """

  Scenario: Disabled restaurant can't be found
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_locales.yml  |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "6" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the restaurant with id "6" has menu:
      | section | product   |
      | Pizzas  | PIZZA     |
      | Burger  | HAMBURGER |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | telephone  | 0033612345678     |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    Given the user "bob" has ordered something for "2018-08-27 12:30:00" at the restaurant with id "6"
    And the user "bob" is authenticated
    When I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/restaurants/6"
    Then the response status code should be 404

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
              "suitableForDiet":["http://schema.org/HalalDiet"],
              "allergens":["NUTS"],
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
        "description":null,
        "enabled":true,
        "depositRefundEnabled": false,
        "depositRefundOptin": true,
        "address":@...@,
        "state":"rush",
        "telephone":null,
        "openingHoursSpecification":@array@,
        "specialOpeningHoursSpecification":@array@,
        "hasMenu":"/api/restaurants/menus/2",
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
        "description": null,
        "enabled":true,
        "depositRefundEnabled": false,
        "depositRefundOptin": true,
        "address":@...@,
        "state":"rush",
        "telephone":null,
        "openingHoursSpecification":@array@,
        "specialOpeningHoursSpecification":@array@,
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

  Scenario: Retrieve restaurant deliveries
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_locales.yml  |
      | products.yml        |
      | restaurants.yml     |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the setting "administrator_email" has value "admin@coopcycle.org"
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | telephone  | 0033612345678     |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" has role "ROLE_RESTAURANT"
    And the restaurant with id "1" belongs to user "bob"
    Given the user "bob" has ordered something for "2020-05-09" at the restaurant with id "1"
    And the user "bob" is authenticated
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/orders/1/accept"
    Then the response status code should be 200
    And the response should be in JSON
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "GET" request to "/api/restaurants/1/deliveries/2020-05-09"
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
            "@id":"/api/deliveries/1",
            "@type":"http://schema.org/ParcelDelivery",
            "id":@integer@,
            "pickup":{
              "@id":"/api/tasks/1",
              "@type":"Task",
              "id":@integer@,
              "status":"TODO",
              "address":{
                "@id":"/api/addresses/1",
                "@type":"http://schema.org/Place",
                "contactName":null,
                "description":null,
                "geo":{
                  "latitude":48.864577,
                  "longitude":2.333338
                },
                "streetAddress":"272, rue Saint Honoré 75001 Paris 1er",
                "telephone":null,
                "name":null
              },
              "comments":@string@,
              "after":"@string@.isDateTime()",
              "before":"@string@.isDateTime()",
              "doneAfter":"@string@.isDateTime()",
              "doneBefore":"@string@.isDateTime()"
            },
            "dropoff":{
              "@id":"/api/tasks/2",
              "@type":"Task",
              "id":@integer@,
              "status":"TODO",
              "address":{
                "@id":"/api/addresses/1",
                "@type":"http://schema.org/Place",
                "contactName":null,
                "description":null,
                "geo":{
                  "latitude":48.864577,
                  "longitude":2.333338
                },
                "streetAddress":"272, rue Saint Honoré 75001 Paris 1er",
                "telephone":null,
                "name":null
              },
              "comments":@string@,
              "after":"@string@.isDateTime()",
              "before":"@string@.isDateTime()",
              "doneAfter":"@string@.isDateTime()",
              "doneBefore":"@string@.isDateTime()"
            }
          }
        ],
        "hydra:totalItems":1
      }
      """

  Scenario: Delete closing rule
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_locales.yml  |
      | restaurants.yml     |
    And the restaurant with id "1" is closed between "2018-08-27 12:00:00" and "2018-08-28 10:00:00"
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_RESTAURANT"
    And the restaurant with id "1" belongs to user "bob"
    And the user "bob" is authenticated
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "DELETE" request to "/api/opening_hours_specifications/1"
    Then the response status code should be 204
