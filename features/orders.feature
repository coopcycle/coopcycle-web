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
          "deliveryAddress": "/api/addresses/4"
        },
        "orderedItem": [{
          "product":  "/api/products/1",
          "quantity": 1
        }, {
          "product":  "/api/products/2",
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
      "courier":null,
      "restaurant":{
        "@id":"/api/restaurants/1",
        "@type":"http://schema.org/Restaurant",
        "name":"Nodaiwa"
      },
      "orderedItem":@array@,
      "delivery":{
        "@id":"@string@.startsWith('/api/deliveries')",
        "@type":"http://schema.org/ParcelDelivery",
        "originAddress":{
          "@id":"@string@.startsWith('/api/addresses')",
          "@type":"Address",
          "geo":{
            "latitude":48.864577,
            "longitude":2.333338
          },
          "streetAddress":"272, rue Saint Honoré 75001 Paris 1er",
          "name":null
        },
        "deliveryAddress":{
          "@id":"@string@.startsWith('/api/addresses')",
          "@type":"Address",
          "geo":{
            "latitude":48.855799,
            "longitude":2.359207
          },
          "streetAddress":"1, rue de Rivoli",
          "name":null
        },
        "status":"WAITING"
      },
      "total":@number@,
      "status":"CREATED"
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
    And the user "bob" has ordered at restaurant "Nodaiwa"
    And the courier is loaded:
      | email    | sarah@coopcycle.org |
      | username | sarah               |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/orders/1/accept" with body:
      """
      {}
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "@context": "/api/contexts/Order",
      "@id":@string@,
      "@type": "http://schema.org/Order",
      "customer":"@string@.startsWith('/api/api_users')",
      "courier":"@string@.startsWith('/api/api_users')",
      "restaurant":{
        "@id":@string@,
        "@type":"http://schema.org/Restaurant",
        "name":"Nodaiwa"
      },
      "orderedItem":@array@,
      "delivery":{
        "@id":@string@,
        "@type":"http://schema.org/ParcelDelivery",
        "originAddress":{
          "@id":@string@,
          "@type":"Address",
          "geo":{
            "latitude":48.864577,
            "longitude":2.333338
          },
          "streetAddress":"272, rue Saint Honoré 75001 Paris 1er",
          "name":null
        },
        "deliveryAddress":{
          "@id": @string@,
          "@type":"Address",
          "geo":{
            "latitude":48.855799,
            "longitude":2.359207
          },
          "streetAddress":"1, rue de Rivoli",
          "name":null
        },
        "status":"DISPATCHED"
      },
      "total":@number@,
      "status":"ACCEPTED"
    }
    """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/orders/1/pick" with body:
      """
      {}
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "@context": "/api/contexts/Order",
      "@id":@string@,
      "@type": "http://schema.org/Order",
      "customer":@string@,
      "courier":@string@,
      "restaurant":{
        "@id":@string@,
        "@type":"http://schema.org/Restaurant",
        "name":"Nodaiwa"
      },
      "orderedItem":@array@,
      "delivery":{
        "@id":@string@,
        "@type":"http://schema.org/ParcelDelivery",
        "originAddress":{
          "@id":@string@,
          "@type":"Address",
          "geo":{
            "latitude":48.864577,
            "longitude":2.333338
          },
          "streetAddress":"272, rue Saint Honoré 75001 Paris 1er",
          "name":null
        },
        "deliveryAddress":{
          "@id": @string@,
          "@type":"Address",
          "geo":{
            "latitude":48.855799,
            "longitude":2.359207
          },
          "streetAddress":"1, rue de Rivoli",
          "name":null
        },
        "status":"PICKED"
      },
      "total":@number@,
      "status":"ACCEPTED"
    }
    """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/orders/1/deliver" with body:
      """
      {}
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "@context": "/api/contexts/Order",
      "@id":@string@,
      "@type": "http://schema.org/Order",
      "customer":@string@,
      "courier":@string@,
      "restaurant":{
        "@id":@string@,
        "@type":"http://schema.org/Restaurant",
        "name":"Nodaiwa"
      },
      "orderedItem":@array@,
      "delivery":{
        "@id":@string@,
        "@type":"http://schema.org/ParcelDelivery",
        "originAddress":{
          "@id":@string@,
          "@type":"Address",
          "geo":{
            "latitude":48.864577,
            "longitude":2.333338
          },
          "streetAddress":"272, rue Saint Honoré 75001 Paris 1er",
          "name":null
        },
        "deliveryAddress":{
          "@id": @string@,
          "@type":"Address",
          "geo":{
            "latitude":48.855799,
            "longitude":2.359207
          },
          "streetAddress":"1, rue de Rivoli",
          "name":null
        },
        "status":"DELIVERED"
      },
      "total":@number@,
      "status":"ACCEPTED"
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
    And the user "bob" has ordered at restaurant "Nodaiwa"
    And the last order from user "bob" has status "ACCEPTED"
    And the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/orders/1/accept" with body:
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
    And the user "bob" has ordered at restaurant "Nodaiwa"
    And the user "bill" is loaded:
      | email    | bill@coopcycle.org |
      | password | 123456             |
    And the user "bill" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bill" sends a "PUT" request to "/api/orders/1/accept" with body:
      """
      {}
      """
    Then the response status code should be 403
    And the response should be in JSON

  Scenario: Courier cannot pick order not accepted by himself
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
    And the user "bob" has ordered at restaurant "Nodaiwa"
    And the last order from user "bob" is accepted by courier "bill"
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/orders/1/pick" with body:
      """
      {}
      """
    Then the response status code should be 403
    And the response should be in JSON
