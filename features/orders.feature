Feature: Orders

  Scenario: Create order
    Given the database is empty
    And the current time is "2017-09-02 11:00:00"
    And the fixtures file "restaurants.yml" is loaded
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
      "customer":{
        "@id":"@string@.startsWith('/api/api_users')",
        "@type":"ApiUser",
        "username":"bob",
        "telephone": "+33612345678"
      },
      "restaurant":{
        "@id":"/api/restaurants/1",
        "@type":"http://schema.org/Restaurant",
        "name":"Nodaiwa"
      },
      "shippingAddress":{
        "@id":"@string@.startsWith('/api/addresses')",
        "@type":"http://schema.org/Place",
        "geo":{
          "latitude":48.855799,
          "longitude":2.359207
        },
        "streetAddress":"1, rue de Rivoli",
        "name":null
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
      "total":@integer@,
      "itemsTotal":@integer@,
      "taxTotal":@integer@,
      "state":"cart",
      "shippedAt":"@string@.startsWith('2017-09-02T12:30:00')",
      "createdAt":@string@
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
        "shippingAddress": "/api/addresses/4",
        "shippedAt": "2017-09-03 12:00:00",
        "items": [{
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
          "propertyPath":"shippedAt",
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
        "shippingAddress": "/api/addresses/4",
        "shippedAt": "2017-09-02 12:30:00",
        "items": [{
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
          "propertyPath":"shippingAddress",
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
        "shippingAddress": "/api/addresses/4",
        "shippedAt": "2017-09-02 12:30:00",
        "items": [{
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
          "propertyPath":"shippedAt",
          "message":@string@
        }
      ]
    }
    """

  Scenario: Amount is not sufficient
    Given the database is empty
    And the current time is "2017-09-02 11:00:00"
    And the fixtures file "restaurants.yml" is loaded
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
          "menuItem": "/api/menu_items/1",
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
