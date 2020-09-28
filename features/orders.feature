Feature: Orders

  Scenario: Not authorized to list orders
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/orders"
    Then the response status code should be 403

  Scenario: Not authorized to retrieve order
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "sarah" is loaded:
      | email      | sarah@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has ordered something at the restaurant with id "1"
    Given the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "GET" request to "/api/orders/1"
    Then the response status code should be 403

  Scenario: User can retrieve own orders
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has ordered something at the restaurant with id "1"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/orders/1"
    Then the response status code should be 200

  Scenario: Restaurant owner can retrieve order
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "sarah" is loaded:
      | email      | sarah@coopcycle.org |
      | password   | 123456            |
    Given the user "bob" has ordered something at the restaurant with id "1"
    And the user "sarah" has role "ROLE_RESTAURANT"
    And the restaurant with id "1" belongs to user "sarah"
    Given the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "GET" request to "/api/orders/1"
    Then the response status code should be 200

  Scenario: Create order (legacy options payload)
    Given the current time is "2017-09-02 11:00:00"
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the setting "brand_name" has value "CoopCycle"
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | telephone  | 0033612345678     |
      | givenName  | Bob               |
      | familyName | Doe               |
    And the user "bob" has delivery address:
      | streetAddress | 1, rue de Rivoli    |
      | postalCode    | 75004               |
      | geo           | 48.855799, 2.359207 |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/orders" with body:
      """
      {
        "restaurant": "/api/restaurants/1",
        "shippingAddress": "/api/addresses/4",
        "shippedAt": "2017-09-02 12:30:00",
        "items": [{
          "product": "PIZZA",
          "quantity": 1,
          "options": [
            "PIZZA_TOPPING_PEPPERONI"
          ]
        }, {
          "product": "HAMBURGER",
          "quantity": 2
        }]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/Order",
      "@id":"@string@.startsWith('/api/orders')",
      "@type":"http://schema.org/Order",
      "customer":@...@,
      "restaurant":{
        "@id":"/api/restaurants/1",
        "@type":"http://schema.org/Restaurant",
        "name":"Nodaiwa",
        "image":@string@,
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
        "telephone": null
      },
      "shippingAddress":{
        "@id":"@string@.startsWith('/api/addresses')",
        "@type":"http://schema.org/Place",
        "geo":{
          "latitude":48.855799,
          "longitude":2.359207
        },
        "streetAddress":"1, rue de Rivoli",
        "name":null,
        "telephone": null
      },
      "items":[
        {
          "id":@integer@,
          "quantity":@integer@,
          "unitPrice":@integer@,
          "total":@integer@,
          "name":@string@,
          "adjustments":@...@
        },
        {
          "id":@integer@,
          "quantity":@integer@,
          "unitPrice":@integer@,
          "total":@integer@,
          "name":@string@,
          "adjustments":@...@
        }
      ],
      "adjustments":@...@,
      "id":@integer@,
      "number":null,
      "total":@integer@,
      "itemsTotal":@integer@,
      "taxTotal":@integer@,
      "state":"cart",
      "notes": null,
      "createdAt":@string@,
      "shippedAt":"@string@.startsWith('2017-09-02T12:30:00')",
      "preparationExpectedAt":null,
      "pickupExpectedAt":null,
      "reusablePackagingEnabled": false
    }
    """

  Scenario: Create order
    Given the current time is "2017-09-02 11:00:00"
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the setting "brand_name" has value "CoopCycle"
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | telephone  | 0033612345678     |
      | givenName  | Bob               |
      | familyName | Doe               |
    And the user "bob" has delivery address:
      | streetAddress | 1, rue de Rivoli    |
      | postalCode    | 75004               |
      | geo           | 48.855799, 2.359207 |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/orders" with body:
      """
      {
        "restaurant": "/api/restaurants/1",
        "shippingAddress": "/api/addresses/4",
        "shippedAt": "2017-09-02 12:30:00",
        "items": [{
          "product": "PIZZA",
          "quantity": 1,
          "options": [
            {"code": "PIZZA_TOPPING_PEPPERONI", "quantity": 1}
          ]
        }, {
          "product": "HAMBURGER",
          "quantity": 2
        }]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/Order",
      "@id":"@string@.startsWith('/api/orders')",
      "@type":"http://schema.org/Order",
      "customer":@...@,
      "restaurant":{
        "@id":"/api/restaurants/1",
        "@type":"http://schema.org/Restaurant",
        "name":"Nodaiwa",
        "image":@string@,
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
        "telephone": null
      },
      "shippingAddress":{
        "@id":"@string@.startsWith('/api/addresses')",
        "@type":"http://schema.org/Place",
        "geo":{
          "latitude":48.855799,
          "longitude":2.359207
        },
        "streetAddress":"1, rue de Rivoli",
        "name":null,
        "telephone": null
      },
      "items":[
        {
          "id":@integer@,
          "quantity":@integer@,
          "unitPrice":@integer@,
          "total":@integer@,
          "name":@string@,
          "adjustments":@...@
        },
        {
          "id":@integer@,
          "quantity":@integer@,
          "unitPrice":@integer@,
          "total":@integer@,
          "name":@string@,
          "adjustments":@...@
        }
      ],
      "adjustments":@...@,
      "id":@integer@,
      "number":null,
      "total":@integer@,
      "itemsTotal":@integer@,
      "taxTotal":@integer@,
      "state":"cart",
      "notes": null,
      "createdAt":@string@,
      "shippedAt":"@string@.startsWith('2017-09-02T12:30:00')",
      "preparationExpectedAt":null,
      "pickupExpectedAt":null,
      "reusablePackagingEnabled": false
    }
    """

  Scenario: Calculate order timing
    Given the current time is "2017-09-02 11:00:00"
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the setting "brand_name" has value "CoopCycle"
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "POST" request to "/api/orders/timing" with body:
      """
      {
        "restaurant": "/api/restaurants/1",
        "shippingAddress": {
          "streetAddress": "190 Rue de Rivoli, Paris",
          "postalCode": "75001",
          "addressLocality": "Paris",
          "geo": {
            "latitude": 48.863814,
            "longitude": 2.3329
          }
        },
        "items": [{
          "product": "PIZZA",
          "quantity": 1,
          "options": [
            "PIZZA_TOPPING_PEPPERONI"
          ]
        }, {
          "product": "HAMBURGER",
          "quantity": 2
        }]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "preparation":"15 minutes",
        "shipping":"1 minutes",
        "asap":"2017-09-02T12:00:00+02:00",
        "range":[
          "2017-09-02T11:55:00+02:00",
          "2017-09-02T12:05:00+02:00"
        ],
        "today":true,
        "fast":false,
        "diff":"55 - 65",
        "choices":@array@,
        "ranges":@array@,
        "behavior":@string@
      }
      """

  Scenario: Get order timing
    Given the current time is "2017-09-02 11:00:00"
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the setting "brand_name" has value "CoopCycle"
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    And the user "bob" has ordered something at the restaurant with id "1"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/orders/1/timing"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "preparation":"@string@.matchRegex('/^[0-9]+ minutes$/')",
        "shipping":"@string@.matchRegex('/^[0-9]+ minutes$/')",
        "asap":"@string@.isDateTime()",
        "range": @array@,
        "today":@boolean@,
        "fast":@boolean@,
        "diff":"@string@.matchRegex('/^[0-9]+ - [0-9]+$/')",
        "choices":@array@,
        "ranges":@array@,
        "behavior":@string@
      }
      """

  Scenario: Get order timing with holidays
    Given the current time is "2017-09-02 11:00:00"
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the setting "brand_name" has value "CoopCycle"
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the restaurant with id "1" is closed between "2017-09-02 09:00:00" and "2017-09-04 11:00:00"
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    And the user "bob" has ordered something at the restaurant with id "1"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/orders/1/timing"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "preparation":"@string@.matchRegex('/^[0-9]+ minutes$/')",
        "shipping":"@string@.matchRegex('/^[0-9]+ minutes$/')",
        "asap":"@string@.startsWith('2017-09-04T11:45:00')",
        "range": @array@,
        "today":@boolean@,
        "fast":@boolean@,
        "diff":"@string@.matchRegex('/^[0-9]+ - [0-9]+$/')",
        "choices":@array@,
        "ranges":@array@,
        "behavior":@string@
      }
      """

  Scenario: Create order with address
    Given the current time is "2017-09-02 11:00:00"
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the setting "brand_name" has value "CoopCycle"
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | telephone  | 0033612345678     |
      | givenName  | Bob               |
      | familyName | Doe               |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/orders" with body:
      """
      {
        "restaurant": "/api/restaurants/1",
        "shippingAddress": {
          "streetAddress": "190 Rue de Rivoli, Paris",
          "postalCode": "75001",
          "addressLocality": "Paris",
          "geo": {
            "latitude": 48.863814,
            "longitude": 2.3329
          }
        },
        "shippedAt": "2017-09-02 12:30:00",
        "items": [{
          "product": "PIZZA",
          "quantity": 1,
          "options": [
            "PIZZA_TOPPING_PEPPERONI"
          ]
        }, {
          "product": "HAMBURGER",
          "quantity": 2
        }]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/Order",
      "@id":"@string@.startsWith('/api/orders')",
      "@type":"http://schema.org/Order",
      "customer":@...@,
      "restaurant":{
        "@id":"/api/restaurants/1",
        "@type":"http://schema.org/Restaurant",
        "name":"Nodaiwa",
        "image":@string@,
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
        "telephone": null
      },
      "shippingAddress":{
        "@id":"@string@.startsWith('/api/addresses')",
        "@type":"http://schema.org/Place",
        "geo":{
          "latitude": 48.863814,
          "longitude": 2.3329
        },
        "streetAddress":"190 Rue de Rivoli, Paris",
        "name":null,
        "telephone": null
      },
      "items":[
        {
          "id":@integer@,
          "quantity":@integer@,
          "unitPrice":@integer@,
          "total":@integer@,
          "name":@string@,
          "adjustments":@...@
        },
        {
          "id":@integer@,
          "quantity":@integer@,
          "unitPrice":@integer@,
          "total":@integer@,
          "name":@string@,
          "adjustments":@...@
        }
      ],
      "adjustments":@...@,
      "id":@integer@,
      "number":null,
      "total":@integer@,
      "itemsTotal":@integer@,
      "taxTotal":@integer@,
      "state":"cart",
      "notes": null,
      "createdAt":@string@,
      "shippedAt":"@string@.startsWith('2017-09-02T12:30:00')",
      "preparationExpectedAt":null,
      "pickupExpectedAt":null,
      "reusablePackagingEnabled": false
    }
    """

  Scenario: Create order without shipping date
    Given the current time is "2017-09-02 11:00:00"
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the setting "brand_name" has value "CoopCycle"
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | telephone  | 0033612345678     |
      | givenName  | Bob               |
      | familyName | Doe               |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/orders" with body:
      """
      {
        "restaurant": "/api/restaurants/1",
        "shippingAddress": {
          "streetAddress": "190 Rue de Rivoli, Paris",
          "postalCode": "75001",
          "addressLocality": "Paris",
          "geo": {
            "latitude": 48.863814,
            "longitude": 2.3329
          }
        },
        "items": [{
          "product": "PIZZA",
          "quantity": 1,
          "options": [
            "PIZZA_TOPPING_PEPPERONI"
          ]
        }, {
          "product": "HAMBURGER",
          "quantity": 2
        }]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"@string@.startsWith('/api/orders')",
        "@type":"http://schema.org/Order",
        "customer":{
          "@id":"@string@.startsWith('/api/customers')",
          "@type":"Customer",
          "username":"bob",
          "email":"bob@coopcycle.org",
          "telephone": "+33612345678",
          "phoneNumber": "+33612345678"
        },
        "restaurant":{
          "@id":"/api/restaurants/1",
          "@type":"http://schema.org/Restaurant",
          "name":"Nodaiwa",
          "image":@string@,
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
          "telephone":"+33612345678"
        },
        "shippingAddress":{
          "@id":"@string@.startsWith('/api/addresses')",
          "@type":"http://schema.org/Place",
          "geo":{
            "latitude": 48.863814,
            "longitude": 2.3329
          },
          "streetAddress":"190 Rue de Rivoli, Paris",
          "name":null,
          "telephone": null
        },
        "items":[
          {
            "id":@integer@,
            "quantity":@integer@,
            "unitPrice":@integer@,
            "total":@integer@,
            "name":@string@,
            "adjustments":@...@
          },
          {
            "id":@integer@,
            "quantity":@integer@,
            "unitPrice":@integer@,
            "total":@integer@,
            "name":@string@,
            "adjustments":@...@
          }
        ],
        "adjustments":@...@,
        "id":@integer@,
        "number":null,
        "total":@integer@,
        "itemsTotal":@integer@,
        "taxTotal":@integer@,
        "state":"cart",
        "notes": null,
        "createdAt":@string@,
        "shippedAt":"@string@.isDateTime()",
        "shippingTimeRange":["2017-09-02T11:55:00+02:00","2017-09-02T12:05:00+02:00"],
        "preparationExpectedAt":null,
        "pickupExpectedAt":null,
        "reusablePackagingEnabled": false,
        "reusablePackagingPledgeReturn": 0,
        "takeaway":false
      }
      """

  Scenario: Create order with missing additional product option
    Given the current time is "2017-09-02 11:00:00"
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code           |
      | PIZZA          |
      | FISH_AND_CHIPS |
    And the setting "brand_name" has value "CoopCycle"
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | telephone  | 0033612345678     |
      | givenName  | Bob               |
      | familyName | Doe               |
    And the user "bob" has delivery address:
      | streetAddress | 1, rue de Rivoli    |
      | postalCode    | 75004               |
      | geo           | 48.855799, 2.359207 |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/orders" with body:
      """
      {
        "restaurant": "/api/restaurants/1",
        "shippingAddress": "/api/addresses/4",
        "shippedAt": "2017-09-02 12:30:00",
        "items": [{
          "product": "PIZZA",
          "quantity": 1,
          "options": [
            "PIZZA_TOPPING_PEPPERONI"
          ]
        }, {
          "product": "FISH_AND_CHIPS",
          "quantity": 2
        }]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/Order",
      "@id":"@string@.startsWith('/api/orders')",
      "@type":"http://schema.org/Order",
      "customer":@...@,
      "restaurant":@...@,
      "shippingAddress":{
        "@id":"/api/addresses/4",
        "@type":"http://schema.org/Place",
        "geo":{
          "latitude":48.855799,
          "longitude":2.359207
        },
        "streetAddress":"1, rue de Rivoli",
        "telephone":null,
        "name":null
      },
      "items":[
        {
          "id":@integer@,
          "quantity":1,
          "unitPrice":900,
          "total":900,
          "name":"Pizza",
          "adjustments":{
            "menu_item_modifier":[
              {
                "id":1,
                "label":"Pepperoni",
                "amount":0
              }
            ]
          }
        },
        {
          "id":@integer@,
          "quantity":2,
          "unitPrice":699,
          "total":1398,
          "name":"Fish and Chips",
          "adjustments":{
            "menu_item_modifier":[
              {
                "id":3,
                "label":"Gluten free",
                "amount":0
              }
            ]
          }
        }
      ],
      "adjustments":@...@,
      "id":@integer@,
      "number":null,
      "total":@integer@,
      "itemsTotal":@integer@,
      "taxTotal":@integer@,
      "state":"cart",
      "notes": null,
      "createdAt":@string@,
      "shippedAt":"@string@.startsWith('2017-09-02T12:30:00')",
      "preparationExpectedAt":null,
      "pickupExpectedAt":null,
      "reusablePackagingEnabled": false
    }
    """

  Scenario: Refuse order when restaurant is closed
    Given the current time is "2017-09-02 12:00:00"
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has delivery address:
      | streetAddress | 1, rue de Rivoli    |
      | geo           | 48.855799, 2.359207 |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/orders" with body:
      """
      {
        "restaurant": "/api/restaurants/1",
        "shippingAddress": "/api/addresses/4",
        "shippedAt": "2017-09-03 12:00:00",
        "items": [{
          "product": "PIZZA",
          "quantity": 1,
          "options": [
            "PIZZA_TOPPING_PEPPERONI"
          ]
        }, {
          "product": "HAMBURGER",
          "quantity": 2
        }]
      }
      """
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/ConstraintViolationList",
      "@type":"ConstraintViolationList",
      "hydra:title":@string@,
      "hydra:description":@string@,
      "violations":[
        {
          "propertyPath":"shippingTimeRange",
          "message":@string@
        }
      ]
    }
    """

  Scenario: Delivery exceeds max distance
    Given the current time is "2017-09-02 11:00:00"
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has delivery address:
      | streetAddress | Louis Blanc         |
      | geo           | 48.882305, 2.365448 |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/orders" with body:
      """
      {
        "restaurant": "/api/restaurants/1",
        "shippingAddress": "/api/addresses/4",
        "shippedAt": "2017-09-02 12:30:00",
        "items": [{
          "product": "PIZZA",
          "quantity": 1,
          "options": [
            "PIZZA_TOPPING_PEPPERONI"
          ]
        }, {
          "product": "HAMBURGER",
          "quantity": 2
        }]
      }
      """
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/ConstraintViolationList",
      "@type":"ConstraintViolationList",
      "hydra:title":@string@,
      "hydra:description":@string@,
      "violations":[
        {
          "propertyPath":"shippingAddress",
          "message":@string@
        }
      ]
    }
    """

  Scenario: Disabled product is ignored
    Given the current time is "2017-09-02 11:00:00"
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
      | SALAD     |
    And the setting "brand_name" has value "CoopCycle"
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | telephone  | 0033612345678     |
      | givenName  | Bob               |
      | familyName | Doe               |
    And the user "bob" has delivery address:
      | streetAddress | 1, rue de Rivoli    |
      | postalCode    | 75004               |
      | geo           | 48.855799, 2.359207 |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/orders" with body:
      """
      {
        "restaurant": "/api/restaurants/1",
        "shippingAddress": "/api/addresses/4",
        "shippedAt": "2017-09-02 12:30:00",
        "items": [{
          "product": "PIZZA",
          "quantity": 3,
          "options": [
            "PIZZA_TOPPING_PEPPERONI"
          ]
        }, {
          "product": "SALAD",
          "quantity": 1
        }]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/orders/1",
        "@type":"http://schema.org/Order",
        "customer":@...@,
        "restaurant":@...@,
        "shippingAddress":@...@,
        "shippedAt":"@string@.isDateTime()",
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn": 0,
        "id":@integer@,
        "number":null,
        "notes":null,
        "items":[
          {
            "id":1,
            "quantity":3,
            "unitPrice":900,
            "total":2700,
            "name":"Pizza",
            "adjustments":{
              "menu_item_modifier":[
                {
                  "id":1,
                  "label":"1 × Pepperoni",
                  "amount":0
                }
              ]
            }
          }
        ],
        "itemsTotal":@integer@,
        "total":@integer@,
        "state":"cart",
        "createdAt":"@string@.isDateTime()",
        "taxTotal":@integer@,
        "preparationExpectedAt":null,
        "pickupExpectedAt":null,
        "adjustments":@array@
      }
      """

  Scenario: Shipping date is in the past
    Given the current time is "2017-09-03 12:00:00"
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has delivery address:
      | streetAddress | 1, rue de Rivoli    |
      | geo           | 48.855799, 2.359207 |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/orders" with body:
      """
      {
        "restaurant": "/api/restaurants/1",
        "shippingAddress": "/api/addresses/4",
        "shippedAt": "2017-09-02 12:30:00",
        "items": [{
          "product": "PIZZA",
          "quantity": 1,
          "options": [
            "PIZZA_TOPPING_PEPPERONI"
          ]
        }, {
          "product": "HAMBURGER",
          "quantity": 2
        }]
      }
      """
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/ConstraintViolationList",
      "@type":"ConstraintViolationList",
      "hydra:title":@string@,
      "hydra:description":@string@,
      "violations":[
        {
          "propertyPath":"shippingTimeRange",
          "message":@string@
        }
      ]
    }
    """

  Scenario: Amount is not sufficient
    Given the current time is "2017-09-02 11:00:00"
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the setting "brand_name" has value "CoopCycle"
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the user "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" has delivery address:
      | streetAddress | 1, rue de Rivoli    |
      | geo           | 48.855799, 2.359207 |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/orders" with body:
      """
      {
        "restaurant": "/api/restaurants/1",
        "shippingAddress": "/api/addresses/4",
        "shippedAt": "2017-09-02 12:30:00",
        "items": [{
          "product": "HAMBURGER",
          "quantity": 1
        }]
      }
      """
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/ConstraintViolationList",
      "@type":"ConstraintViolationList",
      "hydra:title":@string@,
      "hydra:description":@string@,
      "violations":[
        {
          "propertyPath":"total",
          "message":@string@
        }
      ]
    }
    """

  Scenario: Validate cart
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | telephone  | 0033612345678     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the setting "brand_name" has value "CoopCycle"
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    Given the user "bob" has created a cart at restaurant with id "1"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/orders/1/validate"
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ConstraintViolationList",
        "@type":"ConstraintViolationList",
        "hydra:title":"An error occurred",
        "hydra:description":@string@,
        "violations":[
          {
            "propertyPath":"total",
            "message":@string@
          }
        ]
      }
      """
