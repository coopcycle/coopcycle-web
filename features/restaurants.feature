Feature: Manage restaurants

  Scenario: Retrieve the restaurants list
    Given the fixtures files are loaded:
      | products.yml        |
      | restaurants.yml     |
    Given the current time is "2021-12-10 11:00:00"
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
      "hydra:member":[
        {
          "@id":"/api/restaurants/1",
          "facets": {
            "category":["Exclusivités","À la une"],
            "cuisine":["Asiatique", "Italienne"],
            "type":"Restaurant"
          },
          "@*@": "@*@"
        },
        {
          "@id":"/api/restaurants/3",
          "@*@": "@*@"
        },
        {
          "@id":"/api/restaurants/2",
          "@*@": "@*@"
        }
      ],
      "hydra:totalItems":3
    }
    """

  Scenario: Search restaurants
    Given the current time is "2021-12-22 20:00:00"
    And the fixtures files are loaded:
      | products.yml        |
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
              "@type":"GeoCoordinates",
              "latitude":48.846656,
              "longitude":2.369052
            },
            "provider": null,
            "streetAddress":"18, avenue Ledru-Rollin 75012 Paris 12ème",
            "telephone":null,
            "name":null,
            "description": null,
            "contactName": null
          },
          "state":"normal",
          "openingHoursSpecification":[
            {
              "@type":"OpeningHoursSpecification",
              "opens":"19:30",
              "closes":"23:30",
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
          "bannerImage":@string@,
          "fulfillmentMethods":@array@,
          "isOpen":true,
          "nextOpeningDate":"@string@.isDateTime()",
          "hub":null,
          "facets": {
            "@*@": "@*@"
          },
          "loopeatEnabled":false,
          "tags":@array@,
          "badges":@array@,
          "autoAcceptOrdersEnabled": @boolean@,
          "edenredMerchantId": null,
          "edenredTRCardEnabled": false,
          "edenredSyncSent": false,
          "edenredEnabled": false
        }
      ],
      "hydra:totalItems":1,
      "hydra:view":{"@*@":"@*@"},
      "hydra:search":{"@*@":"@*@"}
    }
    """

  Scenario: Retrieve a restaurant
    Given the current time is "2021-12-22 13:00:00"
    And the fixtures files are loaded:
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
          "@type":"GeoCoordinates",
          "latitude":@double@,
          "longitude":@double@
        },
        "provider": null,
        "streetAddress":"272, rue Saint Honoré 75001 Paris 1er",
        "name":null,
        "telephone": null,
        "description": null,
        "contactName": null
      },
      "telephone":"+33612345678",
      "image":@string@,
      "bannerImage":@string@,
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
      "fulfillmentMethods":@array@,
      "potentialAction":{
        "@type":"OrderAction",
        "target":{
          "@type":"EntryPoint",
          "urlTemplate":@string@,
          "inLanguage":"fr",
          "actionPlatform":["http://schema.org/DesktopWebPlatform"]
        },
        "deliveryMethod":["http://purl.org/goodrelations/v1#DeliveryModeOwnFleet"]
      },
      "isOpen":true,
      "nextOpeningDate":"@string@.isDateTime()",
      "hub":null,
      "loopeatEnabled":false,
      "tags":@array@,
      "badges":@array@,
      "autoAcceptOrdersEnabled": @boolean@,
      "edenredMerchantId": null,
      "edenredTRCardEnabled": false,
      "edenredSyncSent": false,
      "edenredEnabled": false
    }
    """

  Scenario: Retrieve a restaurant with cuisines sorted alphabetically
    And the fixtures files are loaded:
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
      "tags": [
        "Asiatique",
        "Italienne"
      ],
      "@*@":"@*@"
    }
    """

  Scenario: Retrieve a closed restaurant
    Given the current time is "2021-12-19 12:00:00"
    Given the fixtures files are loaded:
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
          "@type":"GeoCoordinates",
          "latitude":@double@,
          "longitude":@double@
        },
        "provider": null,
        "streetAddress":"272, rue Saint Honoré 75001 Paris 1er",
        "name":null,
        "telephone": null,
        "description": null,
        "contactName": null
      },
      "telephone":"+33612345678",
      "image":@string@,
      "bannerImage":@string@,
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
      "fulfillmentMethods":@array@,
      "potentialAction":{
        "@type":"OrderAction",
        "target":{
          "@type":"EntryPoint",
          "urlTemplate":@string@,
          "inLanguage":"fr",
          "actionPlatform":["http://schema.org/DesktopWebPlatform"]
        },
        "deliveryMethod":["http://purl.org/goodrelations/v1#DeliveryModeOwnFleet"]
      },
      "isOpen":false,
      "nextOpeningDate":@string@,
      "hub":null,
      "loopeatEnabled":false,
      "tags":@array@,
      "badges":@array@,
      "autoAcceptOrdersEnabled": @boolean@,
      "edenredMerchantId": null,
      "edenredTRCardEnabled": false,
      "edenredSyncSent": false,
      "edenredEnabled": false
    }
    """

  Scenario: Retrieve a restaurant timing (tomorrow)
    Given the current time is "2020-09-17 15:00:00"
    Given the fixtures files are loaded:
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
          "2020-09-18T11:50:00+02:00",
          "2020-09-18T12:00:00+02:00"
        ],
        "today":false,
        "fast": false,
        "diff":"1250 - 1260"
      },
      "collection":null
    }
    """

  Scenario: Retrieve a restaurant timing (today)
    Given the current time is "2020-09-17 12:00:00"
    Given the fixtures files are loaded:
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
          "2020-09-17T12:30:00+02:00",
          "2020-09-17T12:40:00+02:00"
        ],
        "today":true,
        "fast":true,
        "diff":"30 - 40"
      },
      "collection":null
    }
    """

  Scenario: Disabled restaurant can't be found
    Given the fixtures files are loaded:
      | sylius_locales.yml  |
      | payment_methods.yml |
      | products.yml        |
      | restaurants.yml     |
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
    Then the response status code should be 403

  Scenario: Retrieve a restaurant's menu
    Given the fixtures files are loaded:
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
          "@id":"/api/restaurants/menus/1/sections/2",
          "name":"Pizzas",
          "description":null,
          "hasMenuItem":[
            {
              "@type":"MenuItem",
              "@context":"/api/contexts/Product",
              "@id":"/api/products/1",
              "name":"Pizza",
              "description":null,
              "identifier":"PIZZA",
              "enabled":@boolean@,
              "reusablePackagingEnabled":@boolean@,
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
              ],
              "images":[]
            }
          ]
        },
        {
          "@id":"/api/restaurants/menus/1/sections/3",
          "name":"Burgers",
          "description":null,
          "hasMenuItem":[
            {
              "@type":"MenuItem",
              "@context":"/api/contexts/Product",
              "@id":"/api/products/2",
              "name":"Hamburger",
              "description":null,
              "identifier":"HAMBURGER",
              "enabled":@boolean@,
              "reusablePackagingEnabled":@boolean@,
              "suitableForDiet":["http://schema.org/HalalDiet"],
              "allergens":["NUTS"],
              "offers": {
                "@type":"Offer",
                "price":@integer@
              },
              "images":[]
            }
          ]
        }
      ]
    }
    """

  Scenario: Retrieve all menus for a restaurant
    Given the fixtures files are loaded:
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
      "@id":"/api/restaurants/1/menus",
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
        }],
      "hydra:totalItems":2
    }
    """

  Scenario: Restaurant is deliverable
    Given the fixtures files are loaded:
      | products.yml        |
      | restaurants.yml     |
    When I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to "/api/restaurants/1/can-deliver/48.855799,2.359207"
    Then the response status code should be 200
    And the response should be in JSON

  Scenario: Restaurant is not deliverable
    Given the fixtures files are loaded:
      | products.yml        |
      | restaurants.yml     |
    When I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to "/api/restaurants/1/can-deliver/48.882305,2.365448"
    Then the response status code should be 400
    And the response should be in JSON

  Scenario: Change active menu
    Given the fixtures files are loaded:
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
        "hasMenu": "/api/restaurants/menus/4"
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
        "address":{"@*@":"@*@"},
        "state":"normal",
        "telephone":"+33612345678",
        "openingHoursSpecification":@array@,
        "specialOpeningHoursSpecification":@array@,
        "hasMenu":"/api/restaurants/menus/4",
        "image":@string@,
        "loopeatEnabled":false,
        "edenredMerchantId": null,
        "edenredTRCardEnabled": false,
        "edenredSyncSent": false,
        "edenredEnabled": false,
        "hub":null,
        "facets": {
          "@*@": "@*@"
        },
        "tags":@array@,
        "badges":@array@,
        "autoAcceptOrdersEnabled": @boolean@,
        "fulfillmentMethods":@array@,
        "bannerImage":@string@,
        "isOpen":@boolean@,
        "nextOpeningDate":@string@
      }
      """

  Scenario: User has not sufficient access rights
    Given the fixtures files are loaded:
      | products.yml        |
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
      | products.yml        |
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
      | products.yml        |
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
    Then the database entity "AppBundle\Entity\LocalBusiness" should have a property "state" with value "rush"
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
        "address":{"@*@":"@*@"},
        "state":"rush",
        "telephone":"+33612345678",
        "openingHoursSpecification":@array@,
        "specialOpeningHoursSpecification":@array@,
        "image":@string@,
        "loopeatEnabled":false,
        "edenredMerchantId": null,
        "edenredTRCardEnabled": false,
        "edenredSyncSent": false,
        "edenredEnabled": false,
        "hub":null,
        "facets": {
          "@*@": "@*@"
        },
        "tags":@array@,
        "badges":@array@,
        "autoAcceptOrdersEnabled": @boolean@,
        "fulfillmentMethods":@array@,
        "bannerImage":@string@,
        "isOpen":@boolean@,
        "nextOpeningDate":@string@
      }
      """

  Scenario: Retrieve restaurant products
    Given the fixtures files are loaded:
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
            "enabled":@boolean@,
            "identifier":@string@,
            "reusablePackagingEnabled":@boolean@,
            "offers": {
              "@type":"Offer",
              "price":@integer@
            },
            "images":@array@
          },
          {
            "@id":"@string@.startsWith('/api/products')",
            "@type":"Product",
            "id":@integer@,
            "code":@string@,
            "name":@string@,
            "description":null,
            "enabled":@boolean@,
            "identifier":@string@,
            "reusablePackagingEnabled":@boolean@,
            "offers": {
              "@type":"Offer",
              "price":@integer@
            },
            "suitableForDiet":@array@,
            "allergens":@array@,
            "images":@array@
          }],
        "hydra:totalItems":2
      }
      """

  Scenario: Retrieve restaurant product options
    Given the fixtures files are loaded:
      | sylius_locales.yml  |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When I send a "GET" request to "/api/restaurants/1/product_options"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ProductOption",
        "@id":"/api/restaurants/1/product_options",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/product_options/1",
            "@type":"ProductOption",
            "strategy":"free",
            "additional":false,
            "valuesRange":null,
            "code":"PIZZA_TOPPING",
            "values":[
              {
                "@id":"@string@.startsWith('/api/product_option_values')",
                "@type":"ProductOptionValue",
                "price":0,
                "code":@string@,
                "value":@string@,
                "enabled":@boolean@
              },
              {
                "@id":"@string@.startsWith('/api/product_option_values')",
                "@type":"ProductOptionValue",
                "price":0,
                "code":@string@,
                "value":@string@,
                "enabled":@boolean@
              },
              {
                "@id":"@string@.startsWith('/api/product_option_values')",
                "@type":"ProductOptionValue",
                "price":0,
                "code":@string@,
                "value":@string@,
                "enabled":@boolean@
              }],
            "name":"Pizza topping"
          },
          {
            "@id":"/api/product_options/2",
            "@type":"ProductOption",
            "strategy":"free",
            "additional":true,
            "valuesRange":null,
            "code":"GLUTEN_INTOLERANCE",
            "values":[
              {
                "@id":"@string@.startsWith('/api/product_option_values')",
                "@type":"ProductOptionValue",
                "price":0,
                "code":"GLUTEN_FREE",
                "value":"Gluten free",
                "enabled":true
              }
            ],
            "name":"Gluten intolerance"
          }
        ],
        "hydra:totalItems":2
      }
      """

  Scenario: Deleted products are not retrieved
    Given the fixtures files are loaded:
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
            "enabled":@boolean@,
            "identifier":@string@,
            "reusablePackagingEnabled":@boolean@,
            "offers": {
              "@type":"Offer",
              "price":@integer@
            },
            "suitableForDiet":@array@,
            "allergens":@array@,
            "images":@array@
          }],
        "hydra:totalItems":1
      }
      """

  Scenario: Retrieve restaurant deliveries
    Given the fixtures files are loaded:
      | sylius_locales.yml  |
      | payment_methods.yml |
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
    When the user "bob" sends a "GET" request to "/api/restaurants/1/deliveries?date=2020-05-09"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Restaurant",
        "@id":"/api/restaurants/1/deliveries",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/deliveries/1",
            "@type":"http://schema.org/ParcelDelivery",
            "id":@integer@,
            "distance":@integer@,
            "duration":@integer@,
            "polyline":@string@,
            "pickup":{
              "@id":@string@,
              "@type":"Task",
              "id":@integer@,
              "status":"TODO",
              "type":"PICKUP",
              "address":{
                "@id":"/api/addresses/1",
                "@type":"http://schema.org/Place",
                "contactName":null,
                "description":null,
                "geo":{
                  "@type":"GeoCoordinates",
                  "latitude":48.864577,
                  "longitude":2.333338
                },
                "provider": null,
                "streetAddress":"272, rue Saint Honoré 75001 Paris 1er",
                "telephone":null,
                "name":null
              },
              "comments":@string@,
              "after":"@string@.isDateTime()",
              "before":"@string@.isDateTime()",
              "doneAfter":"@string@.isDateTime()",
              "doneBefore":"@string@.isDateTime()",
              "weight": null,
              "packages": [],
              "barcode": @array@,
              "createdAt":"@string@.isDateTime()",
              "tags": [],
              "metadata": {"@*@": "@*@"}
            },
            "dropoff":{
              "@id":@string@,
              "@type":"Task",
              "id":@integer@,
              "status":"TODO",
              "type":"DROPOFF",
              "address":{
                "@id":"/api/addresses/1",
                "@type":"http://schema.org/Place",
                "contactName":null,
                "description":null,
                "geo":{
                  "@type":"GeoCoordinates",
                  "latitude":48.864577,
                  "longitude":2.333338
                },
                "provider": null,
                "streetAddress":"272, rue Saint Honoré 75001 Paris 1er",
                "telephone":null,
                "name":null
              },
              "comments":@string@,
              "after":"@string@.isDateTime()",
              "before":"@string@.isDateTime()",
              "doneAfter":"@string@.isDateTime()",
              "doneBefore":"@string@.isDateTime()",
              "weight":null,
              "packages": [],
              "barcode": @array@,
              "createdAt":"@string@.isDateTime()",
              "tags": [],
              "metadata": {"@*@": "@*@"}
            },
            "tasks":@array@,
            "trackingUrl": @string@
          }
        ],
        "hydra:totalItems":1,
        "hydra:view":{
          "@id":"/api/restaurants/1/deliveries?date=2020-05-09",
          "@type":"hydra:PartialCollectionView"
        }
      }
      """

  Scenario: Delete closing rule
    Given the fixtures files are loaded:
      | sylius_locales.yml  |
      | products.yml        |
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

  Scenario: Create menu
    Given the fixtures files are loaded:
      | sylius_locales.yml  |
      | products.yml        |
      | restaurants.yml     |
    # And the restaurant with id "1" is closed between "2018-08-27 12:00:00" and "2018-08-28 10:00:00"
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_RESTAURANT"
    And the restaurant with id "1" belongs to user "bob"
    And the user "bob" is authenticated
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "POST" request to "/api/restaurants/1/menus" with body:
      """
      {
        "name": "Default"
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Menu",
        "@id":"/api/restaurants/menus/1",
        "@type":"http://schema.org/Menu",
        "name":"Default",
        "identifier":"@string@"
      }
      """

  Scenario: Delete menu
    Given the fixtures files are loaded:
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
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_RESTAURANT"
    And the restaurant with id "1" belongs to user "bob"
    And the user "bob" is authenticated
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "POST" request to "/api/restaurants/1/menus" with body:
      """
      {
        "name": "Other"
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Menu",
        "@id":"/api/restaurants/menus/4",
        "@type":"http://schema.org/Menu",
        "name":"Other",
        "identifier":"@string@"
      }
      """
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "DELETE" request to "/api/restaurants/menus/4"
    Then the response status code should be 204

  Scenario: Edit menu sections
    Given the fixtures files are loaded:
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
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_RESTAURANT"
    And the restaurant with id "1" belongs to user "bob"
    And the user "bob" is authenticated
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "POST" request to "/api/restaurants/menus/1/sections" with body:
      """
      {
        "name": "Salads",
        "description": "Not only for turtles"
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/Menu",
        "@type": "http://schema.org/Menu",
        "@id": "/api/restaurants/menus/1/sections/4",
        "identifier": @string@,
        "name": "Salads",
        "description": "Not only for turtles",
        "hasMenuItem": []
      }
      """
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/restaurants/menus/1/sections/4" with body:
      """
      {
        "products": [
          "/api/products/3",
          "/api/products/4"
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/Menu",
        "@type": "http://schema.org/Menu",
        "@id": "/api/restaurants/menus/1/sections/4",
        "name": "Salads",
        "description": "Not only for turtles",
        "identifier": @string@,
        "hasMenuItem": [
          {
            "@context": "/api/contexts/Product",
            "@id": "/api/products/3",
            "@type": "MenuItem",
            "name":"Salad",
            "description":null,
            "identifier":"SALAD",
            "enabled":false,
            "reusablePackagingEnabled":false,
            "offers":{"@type":"Offer","price":499},
            "images":[]
          },
          {
            "@context": "/api/contexts/Product",
            "@id": "/api/products/4",
            "@type": "MenuItem",
            "name":"Cake",
            "description":null,
            "identifier":"CAKE",
            "enabled":false,
            "reusablePackagingEnabled":false,
            "offers":{"@type":"Offer","price":699},
            "images":[]
          }
        ]
      }
      """
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/restaurants/menus/1/sections/4" with body:
      """
      {
        "products": [
          "/api/products/3"
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/Menu",
        "@type": "http://schema.org/Menu",
        "@id": "/api/restaurants/menus/1/sections/4",
        "name": "Salads",
        "description": "Not only for turtles",
        "identifier": @string@,
        "hasMenuItem": [
          {
            "@context": "/api/contexts/Product",
            "@id": "/api/products/3",
            "@type":"MenuItem",
            "name":"Salad",
            "description":null,
            "identifier":"SALAD",
            "enabled":false,
            "reusablePackagingEnabled":false,
            "offers":{"@type":"Offer","price":499},
            "images":[]
          }
        ]
      }
      """
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/restaurants/menus/1/sections/4" with body:
      """
      {
        "products": [
          "/api/products/4",
          "/api/products/3"
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/Menu",
        "@type": "http://schema.org/Menu",
        "@id": "/api/restaurants/menus/1/sections/4",
        "name": "Salads",
        "description": "Not only for turtles",
        "identifier": @string@,
        "hasMenuItem": [
          {
            "@context": "/api/contexts/Product",
            "@id": "/api/products/4",
            "@type":"MenuItem",
            "name":"Cake",
            "description":null,
            "identifier":"CAKE",
            "enabled":false,
            "reusablePackagingEnabled":false,
            "offers":{"@type":"Offer","price":699},
            "images":[]
          },
          {
            "@context": "/api/contexts/Product",
            "@id": "/api/products/3",
            "@type":"MenuItem",
            "name":"Salad",
            "description":null,
            "identifier":"SALAD",
            "enabled":false,
            "reusablePackagingEnabled":false,
            "offers":{"@type":"Offer","price":499},
            "images":[]
          }
        ]
      }
      """
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "DELETE" request to "/api/restaurants/menus/1/sections/4"
    Then the response status code should be 204

  Scenario: Reorder menu sections
    Given the fixtures files are loaded:
      | sylius_locales.yml  |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the restaurant with id "1" has menu:
      | section  | product   |
      | Pizzas   | PIZZA     |
      | Burgers  | HAMBURGER |
      | Salads   | SALAD     |
      | Desserts | CAKE      |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_RESTAURANT"
    And the restaurant with id "1" belongs to user "bob"
    And the user "bob" is authenticated
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "GET" request to "/api/restaurants/1/menu"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/Menu",
        "@id": "/api/restaurants/menus/1",
        "@type": "http://schema.org/Menu",
        "name": "Menu",
        "identifier": @string@,
        "hasMenuSection": [
          {
            "@id":"/api/restaurants/menus/1/sections/2",
            "name": "Pizzas",
            "description":null,
            "hasMenuItem": @array@
          },
          {
            "@id":"/api/restaurants/menus/1/sections/3",
            "name": "Burgers",
            "description":null,
            "hasMenuItem": @array@
          },
          {
            "@id":"/api/restaurants/menus/1/sections/4",
            "name": "Salads",
            "description":null,
            "hasMenuItem": @array@
          },
          {
            "@id":"/api/restaurants/menus/1/sections/5",
            "name": "Desserts",
            "description":null,
            "hasMenuItem": @array@
          }
        ]
      }
      """
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/restaurants/menus/1" with body:
      """
      {
        "sections": [
          "/api/restaurants/menus/1/sections/4",
          "/api/restaurants/menus/1/sections/5",
          "/api/restaurants/menus/1/sections/2",
          "/api/restaurants/menus/1/sections/3"
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/Menu",
        "@id": "/api/restaurants/menus/1",
        "@type": "http://schema.org/Menu",
        "name": "Menu",
        "identifier": @string@,
        "hasMenuSection": [
            {
              "@id":"/api/restaurants/menus/1/sections/4",
              "name": "Salads",
              "description":null,
              "hasMenuItem": @array@
            },
            {
              "@id":"/api/restaurants/menus/1/sections/5",
              "name": "Desserts",
              "description":null,
              "hasMenuItem": @array@
            },
            {
              "@id":"/api/restaurants/menus/1/sections/2",
              "name": "Pizzas",
              "description":null,
              "hasMenuItem": @array@
            },
            {
              "@id":"/api/restaurants/menus/1/sections/3",
              "name": "Burgers",
              "description":null,
              "hasMenuItem": @array@
            }
        ]
      }
      """
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "GET" request to "/api/restaurants/1/menu"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/Menu",
        "@id": "/api/restaurants/menus/1",
        "@type": "http://schema.org/Menu",
        "name": "Menu",
        "identifier": @string@,
        "hasMenuSection": [
          {
            "@id":"/api/restaurants/menus/1/sections/4",
            "name": "Salads",
            "description":null,
            "hasMenuItem": @array@
          },
          {
            "@id":"/api/restaurants/menus/1/sections/5",
            "name": "Desserts",
            "description":null,
            "hasMenuItem": @array@
          },
          {
            "@id":"/api/restaurants/menus/1/sections/2",
            "name": "Pizzas",
            "description":null,
            "hasMenuItem": @array@
          },
          {
            "@id":"/api/restaurants/menus/1/sections/3",
            "name": "Burgers",
            "description":null,
            "hasMenuItem": @array@
          }
        ]
      }
      """

  Scenario: Updating products in section removes them from previous section
    Given the fixtures files are loaded:
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
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_RESTAURANT"
    And the restaurant with id "1" belongs to user "bob"
    And the user "bob" is authenticated
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "GET" request to "/api/restaurants/1/menu"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/Menu",
        "@id": "/api/restaurants/menus/1",
        "@type": "http://schema.org/Menu",
        "name": "Menu",
        "identifier": @string@,
        "hasMenuSection": [
          {
            "@id":"/api/restaurants/menus/1/sections/2",
            "name": "Pizzas",
            "description":null,
            "hasMenuItem": "@array@.count(1)"
          },
          {
            "@id":"/api/restaurants/menus/1/sections/3",
            "name": "Burger",
            "description":null,
            "hasMenuItem": "@array@.count(1)"
          }
        ]
      }
      """
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/restaurants/menus/1/sections/3" with body:
      """
      {
        "products": [
          "/api/products/1",
          "/api/products/2"
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/Menu",
        "@id": "/api/restaurants/menus/1/sections/3",
        "@type": "http://schema.org/Menu",
        "name": "Burger",
        "description":null,
        "identifier": @string@,
        "hasMenuItem": "@array@.count(2)"
      }
      """
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "GET" request to "/api/restaurants/1/menu"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/Menu",
        "@id": "/api/restaurants/menus/1",
        "@type": "http://schema.org/Menu",
        "name": "Menu",
        "identifier": @string@,
        "hasMenuSection": [
            {
              "@id":"/api/restaurants/menus/1/sections/2",
              "name": "Pizzas",
              "description":null,
              "hasMenuItem": "@array@.count(0)"
            },
            {
              "@id":"/api/restaurants/menus/1/sections/3",
              "name": "Burger",
              "description":null,
              "hasMenuItem": "@array@.count(2)"
            }
        ]
      }
      """

  Scenario: Create & update a shop collection
    Given the fixtures files are loaded:
      | sylius_locales.yml  |
      | products.yml        |
      | restaurants.yml     |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "POST" request to "/api/shop_collections" with body:
      """
      {
        "title": "Our selection",
        "shops": [
          "/api/restaurants/1",
          "/api/restaurants/2"
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ShopCollection",
        "@id":"/api/shop_collections/1",
        "@type":"ShopCollection",
        "title":"Our selection",
        "shops": [
          "/api/restaurants/1",
          "/api/restaurants/2"
        ]
      }
      """
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/shop_collections/1" with body:
      """
      {
        "shops": [
          "/api/restaurants/1",
          "/api/restaurants/2",
          "/api/restaurants/3"
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ShopCollection",
        "@id":"/api/shop_collections/1",
        "@type":"ShopCollection",
        "title":"Our selection",
        "shops": [
          "/api/restaurants/1",
          "/api/restaurants/2",
          "/api/restaurants/3"
        ]
      }
      """
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/shop_collections/1" with body:
      """
      {
        "title": "Our best restaurants"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ShopCollection",
        "@id":"/api/shop_collections/1",
        "@type":"ShopCollection",
        "title":"Our best restaurants",
        "shops": [
          "/api/restaurants/1",
          "/api/restaurants/2",
          "/api/restaurants/3"
        ]
      }
      """
