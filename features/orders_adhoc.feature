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
      | products.yml        |
      | restaurants.yml     |
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
        "customer":"@string@.startsWith('/api/customers')",
        "shippingAddress":null,
        "reusablePackagingEnabled": false,
        "reusablePackagingPledgeReturn": 0,
        "shippingTimeRange": null,
        "notes": null,
        "items": [
          {
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
              }
          },
          {
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
              }
          }
        ],
        "itemsTotal": 1600,
        "total": 1950,
        "restaurant":"@string@.startsWith('/api/restaurants')",
        "shippedAt": null,
        "fulfillmentMethod": "delivery",
        "adjustments": {
          "@*@":"@*@"
        }
      }
    """
