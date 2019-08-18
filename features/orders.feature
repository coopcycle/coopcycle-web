Feature: Orders

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
      "customer":{
        "@id":"@string@.startsWith('/api/users')",
        "@type":"User",
        "username":"bob",
        "email":"bob@coopcycle.org",
        "telephone": "+33612345678",
        "givenName":"Bob",
        "familyName":"Doe"
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
      "customer":{
        "@id":"@string@.startsWith('/api/users')",
        "@type":"User",
        "username":"bob",
        "email":"bob@coopcycle.org",
        "telephone": "+33612345678",
        "givenName":"Bob",
        "familyName":"Doe"
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
        "@id":"@string@.startsWith('/api/users')",
        "@type":"User",
        "username":"bob",
        "email":"bob@coopcycle.org",
        "telephone": "+33612345678",
        "givenName":"Bob",
        "familyName":"Doe"
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
      "id":@integer@,
      "number":null,
      "total":@integer@,
      "itemsTotal":@integer@,
      "taxTotal":@integer@,
      "state":"cart",
      "notes": null,
      "createdAt":@string@,
      "shippedAt":"@string@.isDateTime()",
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
          "propertyPath":"shippedAt",
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

  Scenario: Cannot create order with disabled product
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
          "product": "SALAD",
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
            "propertyPath":"items",
            "message":@string@
          }
        ]
      }
      """

  # Scenario: the delivery is scheduled too soon
  #   Given the database is empty
  #   And the current time is "2017-09-02 12:00:00"
  #   And the fixtures file "restaurants.yml" is loaded
  #   And the user "bob" is loaded:
  #     | email    | bob@coopcycle.org |
  #     | password | 123456            |
  #   And the user "bob" has delivery address:
  #     | streetAddress | 1, rue de Rivoli    |
  #     | geo           | 48.855799, 2.359207 |
  #   And the user "bob" is authenticated
  #   When I add "Content-Type" header equal to "application/ld+json"
  #   And I add "Accept" header equal to "application/ld+json"
  #   And the user "bob" sends a "POST" request to "/api/orders" with body:
  #     """
  #     {
  #       "restaurant": "/api/restaurants/1",
  #       "delivery": {
  #         "date": "2017-09-02 12:30:00",
  #         "deliveryAddress": "/api/addresses/4"
  #       },
  #       "orderedItem": [{
  #         "menuItem": "/api/menu_items/1",
  #         "quantity": 1
  #       }, {
  #         "menuItem": "/api/menu_items/2",
  #         "quantity": 2
  #       }]
  #     }
  #     """
  #   Then the response status code should be 400
  #   And the response should be in JSON
  #   And the JSON should match:
  #   """
  #   {
  #     "@context":"/api/contexts/ConstraintViolationList",
  #     "@type":"ConstraintViolationList",
  #     "hydra:title":@string@,
  #     "hydra:description":@string@,
  #     "violations":[
  #       {
  #         "propertyPath":"delivery.date",
  #         "message":@string@
  #       }
  #     ]
  #   }
  #   """

  Scenario: Shipping date is in the past
    Given the current time is "2017-09-03 12:00:00"
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the setting "default_tax_category" has value "tva_livraison"
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
          "propertyPath":"shippedAt",
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
