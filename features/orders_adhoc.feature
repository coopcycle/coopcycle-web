Feature: Orders Adhoc

  Scenario: User without a restaurant or admin rol can not create an adhoc order
    Given the current time is "2022-09-21 11:00:00"
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_taxation.yml |
      | products.yml        |
      | restaurants.yml     |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/orders/adhoc" with body:
      """
      {
        "restaurant": "/api/restaurants/1",
        "customer": {
          "email": "foo@bar.com",
          "phoneNumber": "+33612345678",
          "fullName": "John Doe"
        },
        "items": [
          {
            "name": "1kg de patatas",
            "price": 500,
            "taxCategory": "tva_conso_immediate"
          }
        ]
      }
      """
    Then the response status code should be 403

  Scenario: Create adhoc order
    Given the current time is "2022-09-21 11:00:00"
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | products.yml        |
      | hubs.yml     |
    And the user "sarah" is loaded:
      | email      | sarah@coopcycle.org |
      | password   | 123456            |
    And the user "sarah" has role "ROLE_RESTAURANT"
    And the restaurant with id "1" belongs to user "sarah"
    Given the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/orders/adhoc" with body:
      """
      {
        "restaurant": "/api/restaurants/1",
        "customer": {
          "email": "foo@bar.com",
          "phoneNumber": "+33612345678",
          "fullName": "John Doe"
        },
        "items": [
          {
            "name": "3kg de patatas",
            "price": 1200,
            "taxCategory": "tva_conso_immediate"
          },
          {
            "name": "2kg de tomates",
            "price": 400,
            "taxCategory": "tva_conso_immediate"
          }
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
    """
      {
        "@context":"\/api\/contexts\/Order",
        "@id":"@string@.startsWith('/api/orders')",
        "@type":"http://schema.org/Order",
        "customer":{
          "@*@":"@*@"
        },
        "vendor": {
          "name": "Nodaiwa",
          "@*@":"@*@"
        },
        "shippingAddress":null,
        "reusablePackagingEnabled": false,
        "reusablePackagingPledgeReturn": 0,
        "reusablePackagingQuantity": @integer@,
        "shippingTimeRange": null,
        "takeaway": false,
        "id": @integer@,
        "number": @string@,
        "notes": null,
        "items": [
          {
              "@id":@string@,
              "@type":"OrderItem",
              "id": @integer@,
              "quantity": 1,
              "unitPrice": 1200,
              "total": @integer@,
              "name": "3kg de patatas",
              "vendor": {
                "@id": "@string@.startsWith('/api/restaurants')",
                "name": "Nodaiwa"
              },
              "adjustments": {
                "@*@":"@*@"
              },
              "player": {"@*@":"@*@"}
          },
          {
              "@id":@string@,
              "@type":"OrderItem",
              "id": @integer@,
              "quantity": 1,
              "unitPrice": 400,
              "total": @integer@,
              "name": "2kg de tomates",
              "vendor": {
                "@id": "@string@.startsWith('/api/restaurants')",
                "name": "Nodaiwa"
              },
              "adjustments": {
                "@*@":"@*@"
              },
              "player": {"@*@":"@*@"}
          }
        ],
        "itemsTotal": 1600,
        "total": 1950,
        "taxTotal": 145,
        "restaurant":{
          "@id":"@string@.startsWith('/api/restaurants')",
          "@type": "http:\/\/schema.org\/Restaurant",
          "name": "Nodaiwa",
          "@*@":"@*@"
        },
        "state": "cart",
        "createdAt":@string@,
        "shippedAt": null,
        "preparationExpectedAt": null,
        "pickupExpectedAt": null,
        "preparationTime": null,
        "shippingTime": null,
        "hasReceipt": false,
        "paymentMethod": "CARD",
        "assignedTo": null,
        "adjustments": {
          "@*@":"@*@"
        },
        "invitation": "@string@||@null@",
        "events":@array@,
        "paymentGateway":@string@,
        "hasEdenredCredentials":@boolean@
      }
    """

  Scenario: Update existing order with a new restaurant
    Given the current time is "2022-09-21 11:00:00"
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | products.yml        |
      | hubs.yml     |
    And the user "sarah" is loaded:
      | email      | sarah@coopcycle.org |
      | password   | 123456            |
    And the user "sarah" has role "ROLE_RESTAURANT"
    And the restaurant with id "1" belongs to user "sarah"
    Given the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/orders/adhoc" with body:
      """
      {
        "restaurant": "/api/restaurants/1",
        "customer": {
          "email": "foo@bar.com",
          "phoneNumber": "+33612345678",
          "fullName": "John Doe"
        },
        "items": [
          {
            "name": "3kg de patatas",
            "price": 1200,
            "taxCategory": "tva_conso_immediate"
          },
          {
            "name": "2kg de tomates",
            "price": 400,
            "taxCategory": "tva_conso_immediate"
          }
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
    """
      {
        "@context":"\/api\/contexts\/Order",
        "@id":"@string@.startsWith('/api/orders')",
        "@type":"http://schema.org/Order",
        "customer":{
          "@*@":"@*@"
        },
        "vendor": {
          "name": "Nodaiwa",
          "@*@":"@*@"
        },
        "shippingAddress":null,
        "reusablePackagingEnabled": false,
        "reusablePackagingPledgeReturn": 0,
        "reusablePackagingQuantity": @integer@,
        "shippingTimeRange": null,
        "takeaway": false,
        "id": @integer@,
        "number": @string@,
        "notes": null,
        "items": [
          {
              "@id":@string@,
              "@type":"OrderItem",
              "id": @integer@,
              "quantity": 1,
              "unitPrice": 1200,
              "total": @integer@,
              "name": "3kg de patatas",
              "vendor": {
                "@id": "@string@.startsWith('/api/restaurants')",
                "name": "Nodaiwa"
              },
              "adjustments": {
                "@*@":"@*@"
              },
              "player": {"@*@":"@*@"}
          },
          {
              "@id":@string@,
              "@type":"OrderItem",
              "id": @integer@,
              "quantity": 1,
              "unitPrice": 400,
              "total": @integer@,
              "name": "2kg de tomates",
              "vendor": {
                "@id": "@string@.startsWith('/api/restaurants')",
                "name": "Nodaiwa"
              },
              "adjustments": {
                "@*@":"@*@"
              },
              "player": {"@*@":"@*@"}
          }
        ],
        "itemsTotal": 1600,
        "total": 1950,
        "taxTotal": 145,
        "restaurant":{
          "@id":"@string@.startsWith('/api/restaurants')",
          "@type": "http:\/\/schema.org\/Restaurant",
          "name": "Nodaiwa",
          "@*@":"@*@"
        },
        "state": "cart",
        "createdAt":@string@,
        "shippedAt": null,
        "preparationExpectedAt": null,
        "pickupExpectedAt": null,
        "preparationTime": null,
        "shippingTime": null,
        "hasReceipt": false,
        "paymentMethod": "CARD",
        "assignedTo": null,
        "adjustments": {
          "@*@":"@*@"
        },
        "invitation": "@string@||@null@",
        "events":@array@,
        "paymentGateway":@string@,
        "hasEdenredCredentials":@boolean@
      }
    """
    When the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_RESTAURANT"
    And the restaurant with id "4" belongs to user "bob"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/orders/adhoc/1" with body:
      """
      {
        "restaurant": "/api/restaurants/4",
        "customer": {
          "email": "foo@bar.com",
          "phoneNumber": "+33612345678",
          "fullName": "John Doe"
        },
        "items": [
          {
            "name": "1kg de zanahorias",
            "price": 4600,
            "taxCategory": "tva_conso_immediate"
          }
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
    """
      {
        "@context":"\/api\/contexts\/Order",
        "@id":"@string@.startsWith('/api/orders')",
        "@type":"http://schema.org/Order",
        "customer":{
          "@*@":"@*@"
        },
        "vendor": {
          "name": "El Mercado",
          "@*@":"@*@"
        },
        "shippingAddress":null,
        "reusablePackagingEnabled": false,
        "reusablePackagingPledgeReturn": 0,
        "reusablePackagingQuantity": @integer@,
        "shippingTimeRange": null,
        "takeaway": false,
        "id": @integer@,
        "number": @string@,
        "notes": null,
        "items": [
          {
              "@id":@string@,
              "@type":"OrderItem",
              "id": @integer@,
              "quantity": 1,
              "unitPrice": 1200,
              "total": @integer@,
              "name": "3kg de patatas",
              "vendor": {
                "@id": "@string@.startsWith('/api/restaurants')",
                "name": "Nodaiwa"
              },
              "adjustments": {
                "@*@":"@*@"
              },
              "player": {"@*@":"@*@"}
          },
          {
              "@id":@string@,
              "@type":"OrderItem",
              "id": @integer@,
              "quantity": 1,
              "unitPrice": 400,
              "total": @integer@,
              "name": "2kg de tomates",
              "vendor": {
                "@id": "@string@.startsWith('/api/restaurants')",
                "name": "Nodaiwa"
              },
              "adjustments": {
                "@*@":"@*@"
              },
              "player": {"@*@":"@*@"}
          },
          {
              "@id":@string@,
              "@type":"OrderItem",
              "id": @integer@,
              "quantity": 1,
              "unitPrice": 4600,
              "total": @integer@,
              "name": "1kg de zanahorias",
              "vendor": {
                "@id": "@string@.startsWith('/api/restaurants')",
                "name": "Wild Buffet"
              },
              "adjustments": {
                "@*@":"@*@"
              },
              "player": {"@*@":"@*@"}
          }
        ],
        "itemsTotal": 6200,
        "total": 6550,
        "taxTotal": 563,
        "restaurant":null,
        "state": "cart",
        "createdAt":@string@,
        "shippedAt": null,
        "preparationExpectedAt": null,
        "pickupExpectedAt": null,
        "preparationTime": null,
        "shippingTime": null,
        "hasReceipt": false,
        "paymentMethod": "CARD",
        "assignedTo": null,
        "adjustments": {
          "@*@":"@*@"
        },
        "invitation": "@string@||@null@",
        "events":@array@,
        "paymentGateway":@string@,
        "hasEdenredCredentials":@boolean@
      }
    """
