Feature: Orders

  Scenario: Create order
    Given the database is empty
    And the fixtures file "restaurants.yml" is loaded
    And the user is loaded:
      | email    | bob@coopcycle.org |
      | username | bob               |
      | password | 123456            |
    And the user "bob" is authenticated
    And the user "bob" has delivery address:
      | streetAddress | 1, rue de Rivoli    |
      | geo           | 48.855799, 2.359207 |
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send an authenticated "POST" request to "/api/orders" with body:
      """
      {
        "restaurant": "/api/restaurants/1",
        "deliveryAddress": "/api/delivery_addresses/1",
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
    And print last response
    And the JSON should match:
    """
    {
      "@context": "/api/contexts/Order",
      "@id": "/api/orders/1",
      "@type": "http://schema.org/Order",
      "customer": "/api/api_users/1",
      "courier":null,
      "restaurant":{
        "@id": "/api/restaurants/1",
        "@type": "http://schema.org/Restaurant",
        "geo":{
          "latitude":48.864577,
          "longitude":2.333338
        },
        "streetAddress": "272, rue Saint Honoré 75001 Paris 1er",
        "name": "Nodaiwa"
      },
      "orderedItem":[
        {
          "@id": "/api/order_items/1",
          "@type": "http://schema.org/OrderItem",
          "product": "/api/products/1",
          "quantity":1
        },
        {
          "@id": "/api/order_items/2",
          "@type": "http://schema.org/OrderItem",
          "product": "/api/products/2",
          "quantity":2
        }
      ],
      "deliveryAddress":{
        "@id": "/api/delivery_addresses/1",
        "@type": "DeliveryAddress",
        "geo":{
          "latitude":0,
          "longitude":0
        },
        "streetAddress": "1, rue de Rivoli",
        "name":null
      },
      "status": "CREATED"
    }
    """