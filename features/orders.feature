Feature: Orders

  Scenario: Create order
    Given the database is empty
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
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/Order",
      "@id":"@string@.startsWith('/api/orders')",
      "@type":"http://schema.org/Order",
      "customer":"@string@.startsWith('/api/api_users')",
      "restaurant":{
        "@id":"/api/restaurants/1",
        "@type":"http://schema.org/Restaurant",
        "name":"Nodaiwa",
        "availabilities":@array@
      },
      "orderedItem":[
        {
          "@id":"@string@.startsWith('/api/order_items')",
          "@type":"http://schema.org/OrderItem",
          "menuItem":"/api/menu_items/1",
          "quantity":1,
          "name":@string@,
          "price":@number@
        },
        {
          "@id":"@string@.startsWith('/api/order_items')",
          "@type":"http://schema.org/OrderItem",
          "menuItem":"/api/menu_items/2",
          "quantity":2,
          "name":@string@,
          "price":@number@
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
        "courier":null,
        "date":"@string@.startsWith('2017-09-02')"
      },
      "total":@number@,
      "status":"CREATED"
    }
    """

  Scenario: Refuse order when restaurant is closed
    Given the database is empty
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

  Scenario: Courier can accept, pick & deliver order
    Given the database is empty
    And the redis database is empty
    And the fixtures file "restaurants.yml" is loaded
    And the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has delivery address:
      | streetAddress | 1, rue de Rivoli    |
      | geo           | 48.855799, 2.359207 |
    And the user "bob" has ordered at restaurant "Nodaiwa" for "2017-09-02 12:30:00"
    And the courier is loaded:
      | email    | sarah@coopcycle.org |
      | username | sarah               |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/deliveries/1/accept" with body:
      """
      {}
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/Delivery",
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
        "@id":"/api/addresses/4",
        "@type":"http://schema.org/Place",
        "geo":{
          "latitude":48.855799,
          "longitude":2.359207
        },
        "streetAddress":"1, rue de Rivoli",
        "name":null
      },
      "courier":"@string@.startsWith('/api/api_users')",
      "status":"DISPATCHED",
      "date":"@string@.startsWith('2017-09-02')"
    }
    """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/deliveries/1/pick" with body:
      """
      {}
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/Delivery",
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
        "@id":"/api/addresses/4",
        "@type":"http://schema.org/Place",
        "geo":{
          "latitude":48.855799,
          "longitude":2.359207
        },
        "streetAddress":"1, rue de Rivoli",
        "name":null
      },
      "courier":"@string@.startsWith('/api/api_users')",
      "status":"PICKED",
      "date":"@string@.startsWith('2017-09-02')"
    }
    """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/deliveries/1/deliver" with body:
      """
      {}
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "@context":"/api/contexts/Delivery",
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
        "@id":"/api/addresses/4",
        "@type":"http://schema.org/Place",
        "geo":{
          "latitude":48.855799,
          "longitude":2.359207
        },
        "streetAddress":"1, rue de Rivoli",
        "name":null
      },
      "courier":"@string@.startsWith('/api/api_users')",
      "status":"DELIVERED",
      "date":"@string@.startsWith('2017-09-02')"
    }
    """

  Scenario: Courier cannot accept order twice
    Given the database is empty
    And the fixtures file "restaurants.yml" is loaded
    And the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has delivery address:
      | streetAddress | 1, rue de Rivoli    |
      | geo           | 48.855799, 2.359207 |
    And the user "bob" has ordered at restaurant "Nodaiwa" for "2017-09-02 12:30:00"
    And the last delivery from user "bob" has status "ACCEPTED"
    And the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/deliveries/1/accept" with body:
      """
      {}
      """
    Then the response status code should be 400
    And the response should be in JSON

  Scenario: User cannot accept order
    Given the database is empty
    And the fixtures file "restaurants.yml" is loaded
    And the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has delivery address:
      | streetAddress | 1, rue de Rivoli    |
      | geo           | 48.855799, 2.359207 |
    And the user "bob" has ordered at restaurant "Nodaiwa" for "2017-09-02 12:30:00"
    And the user "bill" is loaded:
      | email    | bill@coopcycle.org |
      | password | 123456             |
    And the user "bill" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bill" sends a "PUT" request to "/api/deliveries/1/accept" with body:
      """
      {}
      """
    Then the response status code should be 403
    And the response should be in JSON

  Scenario: Courier cannot pick order not dispatched to himself
    Given the database is empty
    And the fixtures file "restaurants.yml" is loaded
    And the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has delivery address:
      | streetAddress | 1, rue de Rivoli    |
      | geo           | 48.855799, 2.359207 |
    And the courier "bill" is loaded:
      | email    | bill@coopcycle.org |
      | password | 123456              |
    And the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "bob" has ordered at restaurant "Nodaiwa" for "2017-09-02 12:30:00"
    And the last delivery from user "bob" is dispatched to courier "bill"
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/deliveries/1/pick" with body:
      """
      {}
      """
    Then the response status code should be 403
    And the response should be in JSON

  Scenario: Delivery exceeds max distance
    Given the database is empty
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
          "propertyPath":"delivery.distance",
          "message":"This value should be less than 3000."
        }
      ]
    }
    """
