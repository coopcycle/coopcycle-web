Feature: Orders

  Scenario: Create order
    Given the database is empty
    And the current time is "2017-09-02 11:00:00"
    And the fixtures file "restaurants.yml" is loaded
    And the setting "brand_name" has value "CoopCycle"
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
        "delivery": {
          "date": "2017-09-02 12:30:00",
          "deliveryAddress": "/api/addresses/4"
        },
        "orderedItem": [{
          "menuItem": "/api/menu_items/1",
          "quantity": 1
        }, {
          "menuItem": "/api/menu_items/2",
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
      "id": @integer@,
      "@type":"http://schema.org/Order",
      "customer":{
        "@id":"@string@.startsWith('/api/api_users')",
        "@type":"ApiUser",
        "username":"bob",
        "telephone": "+33612345678"
      },
      "createdAt": @string@,
      "readyAt": "@string@.startsWith('2017-09-02T12:18')",
      "restaurant":{
        "@id":"/api/restaurants/1",
        "@type":"http://schema.org/Restaurant",
        "name":"Nodaiwa",
        "availabilities":@array@,
        "minimumCartAmount":@integer@,
        "flatDeliveryPrice":@double@
      },
      "orderedItem":[
        {
          "@id":"@string@.startsWith('/api/order_items')",
          "@type":"http://schema.org/OrderItem",
          "menuItem":"@string@.startsWith('/api/menu_items')",
          "quantity":@integer@,
          "name":@string@,
          "price":@number@,
          "modifiers": @array@
        },
        {
          "@id":"@string@.startsWith('/api/order_items')",
          "@type":"http://schema.org/OrderItem",
          "menuItem":"@string@.startsWith('/api/menu_items')",
          "quantity":@integer@,
          "name":@string@,
          "price":@number@,
          "modifiers": @array@
        }
      ],
      "delivery":{
        "@id":"@string@.startsWith('/api/deliveries')",
        "@type":"http://schema.org/ParcelDelivery",
        "originAddress":{
          "@id":"@string@.startsWith('/api/addresses')",
          "@type":"http://schema.org/Place",
          "geo":{
            "latitude":48.864577,
            "longitude":2.333338
          },
          "streetAddress":"272, rue Saint Honoré 75001 Paris 1er",
          "name":null
        },
        "deliveryAddress":{
          "@id":"@string@.startsWith('/api/addresses')",
          "@type":"http://schema.org/Place",
          "geo":{
            "latitude":48.855799,
            "longitude":2.359207
          },
          "streetAddress":"1, rue de Rivoli",
          "name":null
        },
        "status":"WAITING",
        "date":"@string@.startsWith('2017-09-02')",
        "price": @double@,
        "totalExcludingTax":@double@,
        "totalTax":@double@,
        "totalIncludingTax":@double@
      },
      "total":@number@,
      "totalExcludingTax":@double@,
      "totalTax":@double@,
      "totalIncludingTax":@double@,
      "publicUrl":@string@,
      "status":"CREATED",
      "preparationDate":"@string@.startsWith('2017-09-02T11:45:00')"
    }
    """

  Scenario: Refuse order when restaurant is closed
    Given the database is empty
    And the current time is "2017-09-02 12:00:00"
    And the fixtures file "restaurants.yml" is loaded
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
        "delivery": {
          "date": "2017-09-03 12:00:00",
          "deliveryAddress": "/api/addresses/4"
        },
        "orderedItem": [{
          "menuItem": "/api/menu_items/1",
          "quantity": 1
        }, {
          "menuItem": "/api/menu_items/2",
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
          "propertyPath":"delivery.date",
          "message":@string@
        }
      ]
    }
    """

  Scenario: Delivery exceeds max distance
    Given the database is empty
    And the current time is "2017-09-02 11:00:00"
    And the fixtures file "restaurants.yml" is loaded
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
        "delivery": {
          "date": "2017-09-02 12:30:00",
          "deliveryAddress": "/api/addresses/4"
        },
        "orderedItem": [{
          "menuItem": "/api/menu_items/1",
          "quantity": 1
        }, {
          "menuItem": "/api/menu_items/2",
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
          "propertyPath":"delivery.deliveryAddress",
          "message":@string@
        }
      ]
    }
    """

  Scenario: the delivery is scheduled too soon
    Given the database is empty
    And the current time is "2017-09-02 12:00:00"
    And the fixtures file "restaurants.yml" is loaded
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
        "delivery": {
          "date": "2017-09-02 12:30:00",
          "deliveryAddress": "/api/addresses/4"
        },
        "orderedItem": [{
          "menuItem": "/api/menu_items/1",
          "quantity": 1
        }, {
          "menuItem": "/api/menu_items/2",
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
          "propertyPath":"delivery.date",
          "message":@string@
        }
      ]
    }
    """

  Scenario: the delivery is in the past
    Given the database is empty
    And the current time is "2017-09-03 12:00:00"
    And the fixtures file "restaurants.yml" is loaded
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
        "delivery": {
          "date": "2017-09-02 12:30:00",
          "deliveryAddress": "/api/addresses/4"
        },
        "orderedItem": [{
          "menuItem": "/api/menu_items/1",
          "quantity": 1
        }, {
          "menuItem": "/api/menu_items/2",
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
          "propertyPath":"delivery.date",
          "message":@string@
        }
      ]
    }
    """
