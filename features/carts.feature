Feature: Carts

  Scenario: Can't update order when state is cart
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | telephone  | 0033612345678     |
    Given the user "bob" has ordered something at the restaurant with id "1"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/orders/1" with body:
      """
      {
        "restaurant": "/api/restaurants/2"
      }
      """
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
            "propertyPath":"state",
            "message":@string@
          }
        ]
      }
      """

  Scenario: Update cart restaurant
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | telephone  | 0033612345678     |
    Given the user "bob" has created a cart at restaurant with id "1"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/orders/1" with body:
      """
      {
        "restaurant": "/api/restaurants/2"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/orders/1",
        "@type":"http://schema.org/Order",
        "customer":"/api/users/1",
        "restaurant":"/api/restaurants/2",
        "shippingAddress":null,
        "reusablePackagingEnabled":false,
        "notes":null,
        "items":[],
        "itemsTotal":0,
        "total":0,
        "shippedAt":null,
        "adjustments":@...@
      }
      """

  Scenario: Update cart shipping address
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | telephone  | 0033612345678     |
    Given the user "bob" has created a cart at restaurant with id "1"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/orders/1" with body:
      """
      {
        "shippingAddress": {
          "streetAddress": "190 Rue de Rivoli, Paris",
          "postalCode": "75001",
          "addressLocality": "Paris",
          "geo": {
            "latitude": 48.863814,
            "longitude": 2.3329
          }
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/orders/1",
        "@type":"http://schema.org/Order",
        "customer":"/api/users/1",
        "restaurant":"/api/restaurants/1",
        "shippingAddress":{
          "@id":"/api/addresses/4",
          "@type":"http://schema.org/Place",
          "geo":{
            "latitude":48.863814,
            "longitude":2.3329
          },
          "streetAddress":"190 Rue de Rivoli, Paris"
        },
        "shippedAt":null,
        "reusablePackagingEnabled":false,
        "notes":null,
        "items":[],
        "itemsTotal":0,
        "total":0,
        "adjustments":{
          "delivery":[],
          "delivery_promotion":[],
          "reusable_packaging":[]
        }
      }
      """

  Scenario: Update cart shipping address (with session)
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    Given there is a cart at restaurant with id "1"
    And there is a token for the last cart at restaurant with id "1"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send an authenticated "PUT" request to "/api/orders/1" with body:
      """
      {
        "shippingAddress": {
          "streetAddress": "190 Rue de Rivoli, Paris",
          "postalCode": "75001",
          "addressLocality": "Paris",
          "geo": {
            "latitude": 48.863814,
            "longitude": 2.3329
          }
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/orders/1",
        "@type":"http://schema.org/Order",
        "customer":null,
        "restaurant":"/api/restaurants/1",
        "shippingAddress":{
          "@id":"/api/addresses/4",
          "@type":"http://schema.org/Place",
          "geo":{
            "latitude":48.863814,
            "longitude":2.3329
          },
          "streetAddress":"190 Rue de Rivoli, Paris"
        },
        "shippedAt":null,
        "reusablePackagingEnabled":false,
        "notes":null,
        "items":[],
        "itemsTotal":0,
        "total":0,
        "adjustments":{
          "delivery":[],
          "delivery_promotion":[],
          "reusable_packaging":[]
        }
      }
      """

  Scenario: Add items to cart
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
    And the setting "default_tax_category" has value "tva_livraison"
    Given the user "bob" has created a cart at restaurant with id "1"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/orders/1/items" with body:
      """
      {
        "product": "PIZZA",
        "quantity": 2,
        "options": [
          "PIZZA_TOPPING_PEPPERONI"
        ]
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
        "customer":"/api/users/1",
        "restaurant":"/api/restaurants/1",
        "shippingAddress":null,
        "shippedAt":null,
        "reusablePackagingEnabled":false,
        "notes":null,
        "items":[
          {
            "id":@integer@,
            "quantity":2,
            "unitPrice":900,
            "total":1800,
            "name":"Pizza",
            "adjustments":{
              "menu_item_modifier":[
                {
                  "id":@integer@,
                  "label":"1 × Pepperoni",
                  "amount":0
                }
              ]
            }
          }
        ],
        "itemsTotal":1800,
        "total":2150,
        "adjustments":{
          "delivery":[
            {
              "id":@integer@,
              "label":"Livraison",
              "amount":350
            }
          ],
          "delivery_promotion":[],
          "reusable_packaging":[]
        }
      }
      """

  Scenario: Add items to cart (with session)
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
    And the setting "default_tax_category" has value "tva_livraison"
    Given there is a cart at restaurant with id "1"
    And there is a token for the last cart at restaurant with id "1"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send an authenticated "POST" request to "/api/orders/1/items" with body:
      """
      {
        "product": "PIZZA",
        "quantity": 2,
        "options": [
          "PIZZA_TOPPING_PEPPERONI"
        ]
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
        "customer":null,
        "restaurant":"/api/restaurants/1",
        "shippingAddress":null,
        "shippedAt":null,
        "reusablePackagingEnabled":false,
        "notes":null,
        "items":[
          {
            "id":1,
            "quantity":2,
            "unitPrice":900,
            "total":1800,
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
        "itemsTotal":1800,
        "total":2150,
        "adjustments":{
          "delivery":[
            {
              "id":4,
              "label":"Livraison",
              "amount":350
            }
          ],
          "delivery_promotion":[],
          "reusable_packaging":[]
        }
      }
      """

  Scenario: Update cart items quantity
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
    Given the user "bob" has created a cart at restaurant with id "1"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/orders/1/items" with body:
      """
      {
        "product": "PIZZA",
        "quantity": 2,
        "options": [
          "PIZZA_TOPPING_PEPPERONI"
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/orders/1/items/1" with body:
      """
      {
        "quantity": 3
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/orders/1",
        "@type":"http://schema.org/Order",
        "customer":"/api/users/1",
        "restaurant":"/api/restaurants/1",
        "shippingAddress":null,
        "shippedAt":null,
        "reusablePackagingEnabled":false,
        "notes":null,
        "items":[
          {
            "id":1,
            "quantity":3,
            "unitPrice":900,
            "total":2700,
            "name":"Pizza",
            "adjustments":@...@
          }
        ],
        "itemsTotal":2700,
        "total":3050,
        "adjustments":@...@
      }
      """

  Scenario: Update cart items quantity (with session)
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
    And the setting "default_tax_category" has value "tva_livraison"
    Given there is a cart at restaurant with id "1"
    And there is a token for the last cart at restaurant with id "1"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send an authenticated "POST" request to "/api/orders/1/items" with body:
      """
      {
        "product": "PIZZA",
        "quantity": 2,
        "options": [
          "PIZZA_TOPPING_PEPPERONI"
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send an authenticated "PUT" request to "/api/orders/1/items/1" with body:
      """
      {
        "quantity": 3
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/orders/1",
        "@type":"http://schema.org/Order",
        "customer":null,
        "restaurant":"/api/restaurants/1",
        "shippingAddress":null,
        "shippedAt":null,
        "reusablePackagingEnabled":false,
        "notes":null,
        "items":[
          {
            "id":1,
            "quantity":3,
            "unitPrice":900,
            "total":2700,
            "name":"Pizza",
            "adjustments":@...@
          }
        ],
        "itemsTotal":2700,
        "total":3050,
        "adjustments":@...@
      }
      """

  Scenario: Delete cart item
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
    Given the user "bob" has created a cart at restaurant with id "1"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/orders/1/items" with body:
      """
      {
        "product": "PIZZA",
        "quantity": 2,
        "options": [
          "PIZZA_TOPPING_PEPPERONI"
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "DELETE" request to "/api/orders/1/items/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/orders/1",
        "@type":"http://schema.org/Order",
        "customer":"/api/users/1",
        "restaurant":"/api/restaurants/1",
        "shippingAddress":null,
        "shippedAt":null,
        "reusablePackagingEnabled":false,
        "notes":null,
        "items":[],
        "itemsTotal":0,
        "total":350,
        "adjustments":@...@
      }
      """

  Scenario: Delete cart item (with session)
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
    And the setting "default_tax_category" has value "tva_livraison"
    Given there is a cart at restaurant with id "1"
    And there is a token for the last cart at restaurant with id "1"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send an authenticated "POST" request to "/api/orders/1/items" with body:
      """
      {
        "product": "PIZZA",
        "quantity": 2,
        "options": [
          "PIZZA_TOPPING_PEPPERONI"
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send an authenticated "DELETE" request to "/api/orders/1/items/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/orders/1",
        "@type":"http://schema.org/Order",
        "customer":null,
        "restaurant":"/api/restaurants/1",
        "shippingAddress":null,
        "shippedAt":null,
        "reusablePackagingEnabled":false,
        "notes":null,
        "items":[],
        "itemsTotal":0,
        "total":350,
        "adjustments":@...@
      }
      """

  Scenario: Start cart session
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "POST" request to "/api/carts/session" with body:
      """
      {
        "restaurant": "/api/restaurants/1"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "token":@string@,
        "cart":{
          "@context":"/api/contexts/Order",
          "@id":"/api/orders/1",
          "@type":"http://schema.org/Order",
          "customer":null,
          "restaurant":"/api/restaurants/1",
          "shippingAddress":null,
          "shippedAt":null,
          "reusablePackagingEnabled":false,
          "notes":null,
          "items":[],
          "itemsTotal":0,
          "total":0,
          "adjustments":@...@
        }
      }
      """
    Given the client is authenticated with last response token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send an authenticated "POST" request to "/api/carts/session" with body:
      """
      {
        "restaurant": "/api/restaurants/2"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "token":@string@,
        "cart":{
          "@context":"/api/contexts/Order",
          "@id":"/api/orders/1",
          "@type":"http://schema.org/Order",
          "customer":null,
          "restaurant":"/api/restaurants/2",
          "shippingAddress":null,
          "shippedAt":null,
          "reusablePackagingEnabled":false,
          "notes":null,
          "items":[],
          "itemsTotal":0,
          "total":0,
          "adjustments":@...@
        }
      }
      """

  Scenario: Start cart session as an authenticated user
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | telephone  | 0033612345678     |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/carts/session" with body:
      """
      {
        "restaurant": "/api/restaurants/1"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "token":@string@,
        "cart":{
          "@context":"/api/contexts/Order",
          "@id":"/api/orders/1",
          "@type":"http://schema.org/Order",
          "customer":"/api/users/1",
          "restaurant":"/api/restaurants/1",
          "shippingAddress":null,
          "shippedAt":null,
          "reusablePackagingEnabled":false,
          "notes":null,
          "items":[],
          "itemsTotal":0,
          "total":0,
          "adjustments":@...@
        }
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the "X-CoopCycle-Session" header contains last response token
    And the user "bob" sends a "POST" request to "/api/carts/session" with body:
      """
      {
        "restaurant": "/api/restaurants/2"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "token":@string@,
        "cart":{
          "@context":"/api/contexts/Order",
          "@id":"/api/orders/1",
          "@type":"http://schema.org/Order",
          "customer":"/api/users/1",
          "restaurant":"/api/restaurants/2",
          "shippingAddress":null,
          "shippedAt":null,
          "reusablePackagingEnabled":false,
          "notes":null,
          "items":[],
          "itemsTotal":0,
          "total":0,
          "adjustments":@...@
        }
      }
      """

  Scenario: Wrong cart session token
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the restaurant with id "2" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    Given there is a cart at restaurant with id "1"
    And there is a cart at restaurant with id "1"
    And there is a token for the last cart at restaurant with id "1"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send an authenticated "PUT" request to "/api/orders/1" with body:
      """
      {
        "shippingAddress": {
          "streetAddress": "190 Rue de Rivoli, Paris",
          "postalCode": "75001",
          "addressLocality": "Paris",
          "geo": {
            "latitude": 48.863814,
            "longitude": 2.3329
          }
        }
      }
      """
    Then the response status code should be 403

  Scenario: Update cart shipping address with expired session
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    Given there is a cart at restaurant with id "1"
    And there is an expired token for the last cart at restaurant with id "1"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send an authenticated "PUT" request to "/api/orders/1" with body:
      """
      {
        "shippingAddress": {
          "streetAddress": "190 Rue de Rivoli, Paris",
          "postalCode": "75001",
          "addressLocality": "Paris",
          "geo": {
            "latitude": 48.863814,
            "longitude": 2.3329
          }
        }
      }
      """
    Then the response status code should be 401
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "code":401,
        "message":"Expired JWT Token"
      }
      """

  Scenario: Assign cart to customer
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | telephone  | 0033612345678     |
    And the user "bob" is authenticated
    Given there is a cart at restaurant with id "1"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the "X-CoopCycle-Session" header contains a token for the last cart at restaurant with id "1"
    And the user "bob" sends a "PUT" request to "/api/orders/1/assign" with body:
      """
      {}
      """
    Then print last response
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/orders/1",
        "@type":"http://schema.org/Order",
        "customer":"/api/users/1",
        "restaurant":"/api/restaurants/1",
        "shippingAddress":null,
        "shippedAt":null,
        "reusablePackagingEnabled":false,
        "notes":null,
        "items":[

        ],
        "itemsTotal":0,
        "total":0,
        "adjustments":{
          "delivery":[],
          "delivery_promotion":[],
          "reusable_packaging":[]
        }
      }
      """

  Scenario: Can't assign cart to customer
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | telephone  | 0033612345678     |
    And the user "bob" is authenticated
    Given there is a cart at restaurant with id "1"
    And there is a cart at restaurant with id "1"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the "X-CoopCycle-Session" header contains a token for the last cart at restaurant with id "1"
    And the user "bob" sends a "PUT" request to "/api/orders/1/assign" with body:
      """
      {}
      """
    Then print last response
    Then the response status code should be 403
    And the response should be in JSON
