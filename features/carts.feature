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
            "message":@string@,
            "code":null
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
        "customer":"/api/customers/1",
        "restaurant":"/api/restaurants/2",
        "shippingAddress":null,
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn": 0,
        "notes":null,
        "items":[],
        "itemsTotal":0,
        "total":350,
        "shippedAt":null,
        "shippingTimeRange":null,
        "adjustments":{
          "delivery":[
            {
              "id":@integer@,
              "label":@string@,
              "amount":@integer@
            }
          ],
          "delivery_promotion":[],
          "order_promotion":[],
          "reusable_packaging":[],
          "tax":[]
        },
        "fulfillmentMethod":"delivery"
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
        "customer":"/api/customers/1",
        "restaurant":"/api/restaurants/1",
        "shippingAddress":{
          "@id":"/api/addresses/4",
          "@type":"http://schema.org/Place",
          "geo":{
            "@type":"GeoCoordinates",
            "latitude":48.863814,
            "longitude":2.3329
          },
          "streetAddress":"190 Rue de Rivoli, Paris",
          "telephone": null
        },
        "shippedAt":null,
        "shippingTimeRange":null,
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn": 0,
        "notes":null,
        "items":[],
        "itemsTotal":0,
        "total":350,
        "adjustments":{
          "delivery":[
            {
              "id":@integer@,
              "label":@string@,
              "amount":@integer@
            }
          ],
          "delivery_promotion":[],
          "order_promotion":[],
          "reusable_packaging":[],
          "tax":[]
        },
        "fulfillmentMethod":"delivery"
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
            "@type":"GeoCoordinates",
            "latitude":48.863814,
            "longitude":2.3329
          },
          "streetAddress":"190 Rue de Rivoli, Paris",
          "telephone":null
        },
        "shippedAt":null,
        "shippingTimeRange":null,
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn": 0,
        "notes":null,
        "items":[],
        "itemsTotal":0,
        "total":350,
        "adjustments":{
          "delivery":[
            {
              "id":@integer@,
              "label":@string@,
              "amount":@integer@
            }
          ],
          "delivery_promotion":[],
          "order_promotion":[],
          "reusable_packaging":[],
          "tax":[]
        },
        "fulfillmentMethod": "delivery"
      }
      """

  Scenario: Update cart shipping time (legacy)
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
        "shippedAt": "2020-04-09 20:00:00"
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
        "customer":"/api/customers/1",
        "restaurant":"/api/restaurants/1",
        "shippingAddress":null,
        "shippedAt":"2020-04-09T20:00:00+02:00",
        "shippingTimeRange":[
          "2020-04-09T19:55:00+02:00",
          "2020-04-09T20:05:00+02:00"
        ],
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn": 0,
        "notes":null,
        "items":[],
        "itemsTotal":0,
        "total":350,
        "adjustments":{
          "delivery":[
            {
              "id":@integer@,
              "label":@string@,
              "amount":@integer@
            }
          ],
          "delivery_promotion":[],
          "order_promotion":[],
          "reusable_packaging":[],
          "tax":[]
        },
        "fulfillmentMethod":"delivery"
      }
      """

  Scenario: Update cart shipping time
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
        "shippingTimeRange":[
          "2020-04-09T20:00:00+02:00",
          "2020-04-09T20:10:00+02:00"
        ]
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
        "customer":"/api/customers/1",
        "restaurant":"/api/restaurants/1",
        "shippingAddress":null,
        "shippedAt":"2020-04-09T20:05:00+02:00",
        "shippingTimeRange":[
          "2020-04-09T20:00:00+02:00",
          "2020-04-09T20:10:00+02:00"
        ],
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn": 0,
        "notes":null,
        "items":[],
        "itemsTotal":0,
        "total":350,
        "adjustments":{
          "delivery":[
            {
              "id":@integer@,
              "label":@string@,
              "amount":@integer@
            }
          ],
          "delivery_promotion":[],
          "order_promotion":[],
          "reusable_packaging":[],
          "tax":[]
        },
        "fulfillmentMethod":"delivery"
      }
      """

  Scenario: Clear cart shipping time
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
        "shippingTimeRange":null
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
        "customer":"/api/customers/1",
        "restaurant":"/api/restaurants/1",
        "shippingAddress":null,
        "shippedAt":null,
        "shippingTimeRange":null,
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn": 0,
        "notes":null,
        "items":[],
        "itemsTotal":0,
        "total":350,
        "adjustments":{
          "delivery":[
            {
              "id":@integer@,
              "label":@string@,
              "amount":@integer@
            }
          ],
          "delivery_promotion":[],
          "order_promotion":[],
          "reusable_packaging":[],
          "tax":[]
        },
        "fulfillmentMethod":"delivery"
      }
      """

  Scenario: Add promotion coupon (with session)
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
      | promotions.yml      |
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
        "promotionCoupon": "FREE_DELIVERY"
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
        "shippingTimeRange": null,
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn": 0,
        "notes":null,
        "items":[],
        "itemsTotal":0,
        "total":0,
        "adjustments":{
          "delivery":[
            {
              "id":3,
              "label":"Livraison",
              "amount":350
            }
          ],
          "delivery_promotion":[
            {
              "id":1,
              "label":"Free delivery",
              "amount":-350
            }
          ],
          "order_promotion":[],
          "reusable_packaging":[],
          "tax":@array@
        },
        "fulfillmentMethod":"delivery"
      }
      """

  Scenario: Enable reusable packaging (with session)
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
        "reusablePackagingEnabled": true
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
        "shippingTimeRange": null,
        "reusablePackagingEnabled":true,
        "reusablePackagingPledgeReturn": 0,
        "notes":null,
        "items":[],
        "itemsTotal":0,
        "total":350,
        "adjustments":{
          "delivery":[
            {
              "id":2,
              "label":"Livraison",
              "amount":350
            }
          ],
          "delivery_promotion":[],
          "order_promotion":[],
          "reusable_packaging":[],
          "tax": @array@
        },
        "fulfillmentMethod":"delivery"
      }
      """

  Scenario: Add items to cart (legacy options payload)
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
    And the setting "subject_to_vat" has value "1"
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
        "customer":"/api/customers/1",
        "restaurant":"/api/restaurants/1",
        "shippingAddress":null,
        "shippedAt":null,
        "shippingTimeRange": null,
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn": 0,
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
                  "id":@string@,
                  "label":"1 × Pepperoni",
                  "amount":0
                }
              ],
              "tax":[
                {
                  "id":@string@,
                  "label":"TVA 10%",
                  "amount":@integer@
                }
              ]
            },
            "vendor": {
              "@id":@string@,
              "name":@string@
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
          "order_promotion":[],
          "reusable_packaging":[],
          "tax":[
            {
              "id":@integer@,
              "label":"TVA 20%",
              "amount":@integer@
            }
          ]
        },
        "fulfillmentMethod":"delivery"
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
    And the setting "subject_to_vat" has value "1"
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
          {"code": "PIZZA_TOPPING_PEPPERONI", "quantity": 1}
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
        "customer":"/api/customers/1",
        "restaurant":"/api/restaurants/1",
        "shippingAddress":null,
        "shippedAt":null,
        "shippingTimeRange": null,
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn": 0,
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
                  "id":@string@,
                  "label":"1 × Pepperoni",
                  "amount":0
                }
              ],
              "tax":[
                {
                  "id":@string@,
                  "label":"TVA 10%",
                  "amount":@integer@
                }
              ]
            },
            "vendor": {
              "@id":@string@,
              "name":@string@
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
          "order_promotion":[],
          "reusable_packaging":[],
          "tax":[
            {
              "id":@integer@,
              "label":"TVA 20%",
              "amount":@integer@
            }
          ]
        },
        "fulfillmentMethod":"delivery"
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
    And the setting "subject_to_vat" has value "1"
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
        "shippingTimeRange": null,
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn": 0,
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
                  "id":@string@,
                  "label":"1 × Pepperoni",
                  "amount":0
                }
              ],
              "tax":[
                {
                  "id":@string@,
                  "label":"TVA 10%",
                  "amount":@integer@
                }
              ]
            },
            "vendor": {
              "@id":@string@,
              "name":@string@
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
          "order_promotion":[],
          "reusable_packaging":[],
          "tax":[
            {
              "id":@integer@,
              "label":"TVA 20%",
              "amount":@integer@
            }
          ]
        },
        "fulfillmentMethod":"delivery"
      }
      """

  Scenario: Obtain reusable packaging potential action (with session)
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | telephone  | 0033612345678     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the restaurant with id "1" has deposit-refund enabled
    And the product with code "PIZZA" has reusable packaging enabled with unit "1"
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
        "shippingTimeRange": null,
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn": 0,
        "notes":null,
        "items":@array@,
        "itemsTotal":1800,
        "total":2150,
        "adjustments":@...@,
        "potentialAction":[
          {
            "@context":"http://schema.org",
            "@type":"EnableReusablePackagingAction",
            "actionStatus":"PotentialActionStatus",
            "description":@string@
          }
        ],
        "fulfillmentMethod": "delivery"
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
    And the setting "subject_to_vat" has value "1"
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
        "customer":"/api/customers/1",
        "restaurant":"/api/restaurants/1",
        "shippingAddress":null,
        "shippedAt":null,
        "shippingTimeRange": null,
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn": 0,
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
        "adjustments":@...@,
        "fulfillmentMethod":"delivery"
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
    And the setting "subject_to_vat" has value "1"
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
        "shippingTimeRange": null,
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn": 0,
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
        "adjustments":@...@,
        "fulfillmentMethod":"delivery"
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
    And the setting "subject_to_vat" has value "1"
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
        "customer":"/api/customers/1",
        "restaurant":"/api/restaurants/1",
        "shippingAddress":null,
        "shippedAt":null,
        "shippingTimeRange": null,
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn": 0,
        "notes":null,
        "items":[],
        "itemsTotal":0,
        "total":350,
        "adjustments":@...@,
        "fulfillmentMethod":"delivery"
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
    And the setting "subject_to_vat" has value "1"
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
        "shippingTimeRange": null,
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn": 0,
        "notes":null,
        "items":[],
        "itemsTotal":0,
        "total":350,
        "adjustments":@...@,
        "fulfillmentMethod":"delivery"
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
          "shippingTimeRange": null,
          "reusablePackagingEnabled":false,
          "reusablePackagingPledgeReturn": 0,
          "notes":null,
          "items":[],
          "itemsTotal":0,
          "total":0,
          "adjustments":@...@,
          "fulfillmentMethod":"delivery"
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
          "shippingTimeRange": null,
          "reusablePackagingEnabled":false,
          "reusablePackagingPledgeReturn": 0,
          "notes":null,
          "items":[],
          "itemsTotal":0,
          "total":0,
          "adjustments":@...@,
          "fulfillmentMethod":"delivery"
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
          "customer":"/api/customers/1",
          "restaurant":"/api/restaurants/1",
          "shippingAddress":null,
          "shippedAt":null,
          "shippingTimeRange": null,
          "reusablePackagingEnabled":false,
          "reusablePackagingPledgeReturn": 0,
          "notes":null,
          "items":[],
          "itemsTotal":0,
          "total":0,
          "adjustments":@...@,
          "fulfillmentMethod":"delivery"
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
          "customer":"/api/customers/1",
          "restaurant":"/api/restaurants/2",
          "shippingAddress":null,
          "shippedAt":null,
          "shippingTimeRange": null,
          "reusablePackagingEnabled":false,
          "reusablePackagingPledgeReturn": 0,
          "notes":null,
          "items":[],
          "itemsTotal":0,
          "total":0,
          "adjustments":@...@,
          "fulfillmentMethod": "delivery"
        }
      }
      """

  Scenario: Start cart session (with collection fulfillment method)
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "POST" request to "/api/carts/session" with body:
      """
      {
        "restaurant": "/api/restaurants/4"
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
          "restaurant":"/api/restaurants/4",
          "shippingAddress":null,
          "shippedAt":null,
          "shippingTimeRange": null,
          "reusablePackagingEnabled":false,
          "reusablePackagingPledgeReturn": 0,
          "notes":null,
          "items":[],
          "itemsTotal":0,
          "total":0,
          "adjustments":@...@,
          "fulfillmentMethod":"collection"
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
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/orders/1",
        "@type":"http://schema.org/Order",
        "customer":"/api/customers/1",
        "restaurant":"/api/restaurants/1",
        "shippingAddress":null,
        "shippedAt":null,
        "shippingTimeRange": null,
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn": 0,
        "notes":null,
        "items":[],
        "itemsTotal":0,
        "total":350,
        "adjustments":{
          "delivery":[
            {
              "id":@integer@,
              "label":@string@,
              "amount":@integer@
            }
          ],
          "delivery_promotion":[],
          "order_promotion":[],
          "reusable_packaging":[],
          "tax":[]
        },
        "fulfillmentMethod":"delivery"
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
    Then the response status code should be 403
    And the response should be in JSON

  Scenario: Assign cart to guest customer
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the setting "guest_checkout_enabled" has value "1"
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
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
          "shippingTimeRange": null,
          "reusablePackagingEnabled":false,
          "reusablePackagingPledgeReturn": 0,
          "notes":null,
          "items":[],
          "itemsTotal":0,
          "total":0,
          "adjustments":@...@,
          "fulfillmentMethod":"delivery"
        }
      }
      """
    Given the client is authenticated with last response token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send an authenticated "PUT" request to "/api/orders/1/assign" with body:
      """
      {
        "guest": true,
        "email": "guest@coopcycle.org",
        "telephone": "+33193166989"
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
        "customer":"/api/customers/1",
        "restaurant":"/api/restaurants/1",
        "shippingAddress":null,
        "shippedAt":null,
        "shippingTimeRange": null,
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn": 0,
        "notes":null,
        "items":[],
        "itemsTotal":0,
        "total":350,
        "adjustments":{
          "delivery":[
            {
              "id":@integer@,
              "label":@string@,
              "amount":@integer@
            }
          ],
          "delivery_promotion":[],
          "order_promotion":[],
          "reusable_packaging":[],
          "tax":[]
        },
        "fulfillmentMethod":"delivery"
      }
      """
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
          "@id":"/api/orders/2",
          "@type":"http://schema.org/Order",
          "customer":null,
          "restaurant":"/api/restaurants/1",
          "shippingAddress":null,
          "shippedAt":null,
          "shippingTimeRange": null,
          "reusablePackagingEnabled":false,
          "reusablePackagingPledgeReturn": 0,
          "notes":null,
          "items":[],
          "itemsTotal":0,
          "total":0,
          "adjustments":@...@,
          "fulfillmentMethod":"delivery"
        }
      }
      """
    Given the client is authenticated with last response token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send an authenticated "PUT" request to "/api/orders/2/assign" with body:
      """
      {
        "guest": true,
        "email": "guest_2@coopcycle.org",
        "telephone": "+33193166989"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/orders/2",
        "@type":"http://schema.org/Order",
        "customer":"/api/customers/2",
        "restaurant":"/api/restaurants/1",
        "shippingAddress":null,
        "shippedAt":null,
        "shippingTimeRange": null,
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn": 0,
        "notes":null,
        "items":[],
        "itemsTotal":0,
        "total":350,
        "adjustments":{
          "delivery":[
            {
              "id":@integer@,
              "label":@string@,
              "amount":@integer@
            }
          ],
          "delivery_promotion":[],
          "order_promotion":[],
          "reusable_packaging":[],
          "tax":[]
        },
        "fulfillmentMethod":"delivery"
      }
      """
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
          "@id":"/api/orders/3",
          "@type":"http://schema.org/Order",
          "customer":null,
          "restaurant":"/api/restaurants/1",
          "shippingAddress":null,
          "shippedAt":null,
          "shippingTimeRange": null,
          "reusablePackagingEnabled":false,
          "reusablePackagingPledgeReturn": 0,
          "notes":null,
          "items":[],
          "itemsTotal":0,
          "total":0,
          "adjustments":@...@,
          "fulfillmentMethod":"delivery"
        }
      }
      """
    Given the client is authenticated with last response token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send an authenticated "PUT" request to "/api/orders/3/assign" with body:
      """
      {
        "guest": true,
        "email": "guest@coopcycle.org",
        "telephone": "+33193166989"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/orders/3",
        "@type":"http://schema.org/Order",
        "customer":"/api/customers/1",
        "restaurant":"/api/restaurants/1",
        "shippingAddress":null,
        "shippedAt":null,
        "shippingTimeRange": null,
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn": 0,
        "notes":null,
        "items":[],
        "itemsTotal":0,
        "total":350,
        "adjustments":{
          "delivery":[
            {
              "id":@integer@,
              "label":@string@,
              "amount":@integer@
            }
          ],
          "delivery_promotion":[],
          "order_promotion":[],
          "reusable_packaging":[],
          "tax":[]
        },
        "fulfillmentMethod":"delivery"
      }
      """

  Scenario: Update cart with invalid phone number
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
          },
          "telephone": "+336123"
        }
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
             "propertyPath":"shippingAddress.telephone",
             "message":@string@,
             "code":@string@
          }
        ]
      }
      """

  Scenario: Update cart fulfillment method
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | telephone  | 0033612345678     |
    Given the user "bob" has created a cart at restaurant with id "2"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/orders/1" with body:
      """
      {
        "fulfillmentMethod": "collection"
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
        "customer":@string@,
        "restaurant":@string@,
        "shippingAddress":null,
        "shippedAt":null,
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn":0,
        "shippingTimeRange":null,
        "notes":null,
        "items":[],
        "itemsTotal":0,
        "total":0,
        "fulfillmentMethod":"collection",
        "adjustments":{
          "delivery":[],
          "delivery_promotion":[],
          "order_promotion":[],
          "reusable_packaging":[],
          "tax":[]
        }
      }
      """

  Scenario: Update cart fulfillment method (not enabled)
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
        "fulfillmentMethod": "collection"
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
            "propertyPath":"takeaway",
            "message":@string@,
            "code":@string@
          }
        ]
      }
      """

  Scenario: Get cart timing (with session)
    Given the current time is "2020-10-02 11:00:00"
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
    And the setting "subject_to_vat" has value "1"
    Given there is a cart at restaurant with id "1"
    And there is a token for the last cart at restaurant with id "1"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send an authenticated "GET" request to "/api/orders/1/timing"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "behavior":"asap",
        "preparation":"10 minutes",
        "shipping":"10 minutes",
        "asap":"2020-10-02T12:05:00+02:00",
        "range":[
          "2020-10-02T12:00:00+02:00",
          "2020-10-02T12:10:00+02:00"
        ],
        "today":true,
        "fast":false,
        "diff":"60 - 70",
        "ranges":@array@,
        "choices":@array@
      }
      """

  Scenario: Validate cart (with session)
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
    And the setting "subject_to_vat" has value "1"
    Given there is a cart at restaurant with id "1"
    And there is a token for the last cart at restaurant with id "1"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send an authenticated "GET" request to "/api/orders/1/validate"
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
            "message":@string@,
            "code":null
          }
        ]
      }
      """

  Scenario: Start cart session with address
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "POST" request to "/api/carts/session" with body:
      """
      {
        "restaurant": "/api/restaurants/1",
        "shippingAddress":{
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
        "token":@string@,
        "cart":{
          "@context":"/api/contexts/Order",
          "@id":"/api/orders/1",
          "@type":"http://schema.org/Order",
          "customer":null,
          "restaurant":"/api/restaurants/1",
          "shippingAddress":{"@*@":"@*@"},
          "shippedAt":null,
          "shippingTimeRange": null,
          "reusablePackagingEnabled":false,
          "reusablePackagingPledgeReturn": 0,
          "notes":null,
          "items":[],
          "itemsTotal":0,
          "total":0,
          "adjustments":@...@,
          "fulfillmentMethod":"delivery"
        }
      }
      """

  Scenario: Start cart session as an authenticated user with existing address not belonging to user
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | telephone  | 0033612345678     |
    And the user "bob" has delivery address:
      | streetAddress | 1, rue de Rivoli    |
      | postalCode    | 75004               |
      | geo           | 48.855799, 2.359207 |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/carts/session" with body:
      """
      {
        "restaurant": "/api/restaurants/1",
        "shippingAddress": "/api/addresses/1"
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
          "customer":"/api/customers/1",
          "restaurant":"/api/restaurants/1",
          "shippingAddress":null,
          "shippedAt":null,
          "shippingTimeRange": null,
          "reusablePackagingEnabled":false,
          "reusablePackagingPledgeReturn": 0,
          "notes":null,
          "items":[],
          "itemsTotal":0,
          "total":0,
          "adjustments":@...@,
          "fulfillmentMethod":"delivery"
        }
      }
      """

  Scenario: Start cart session as an authenticated user with existing address
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | telephone  | 0033612345678     |
    And the user "bob" has delivery address:
      | streetAddress | 1, rue de Rivoli    |
      | postalCode    | 75004               |
      | geo           | 48.855799, 2.359207 |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/carts/session" with body:
      """
      {
        "restaurant": "/api/restaurants/1",
        "shippingAddress": "/api/addresses/4"
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
          "customer":"/api/customers/1",
          "restaurant":"/api/restaurants/1",
          "shippingAddress":{"@*@":"@*@"},
          "shippedAt":null,
          "shippingTimeRange": null,
          "reusablePackagingEnabled":false,
          "reusablePackagingPledgeReturn": 0,
          "notes":null,
          "items":[],
          "itemsTotal":0,
          "total":0,
          "adjustments":@...@,
          "fulfillmentMethod":"delivery"
        }
      }
      """
