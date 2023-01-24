Feature: Stripe Payment Methods

Scenario: Get user saved stripe payment methods
  Given the fixtures files are loaded:
    | sylius_channels.yml |
  And the setting "stripe_test_secret_key" has value "sk_test_123"
  And the setting "stripe_test_publishable_key" has value "pk_1234567890"
  Given stripe client is ready to use
  And the user "bob" is loaded:
    | email            | bob@coopcycle.org |
    | password         | 123456            |
    | telephone        | 0033612345678     |
    | givenName        | Bob               |
    | familyName       | Doe               |
    | stripeCustomerId | cus_N0mGZu2XaiQxkf       |
  And the user "bob" is authenticated
  When I add "Content-Type" header equal to "application/ld+json"
  And I add "Accept" header equal to "application/ld+json"
  And the user "bob" sends a "GET" request to "/api/me/stripe-payment-methods"
  Then the response status code should be 200
  And the response should be in JSON
  And the JSON should match:
    """
    {
      "@context":{"@*@":"@*@"},
      "@type":"StripePaymentMethodsOutput",
      "@id":@string@,
      "methods":[
        {
          "id":"@string@.startsWith('pm_')",
          "@*@":"@*@"
        }
      ]
    }
    """

Scenario: Stripe clone payment method
  Given the current time is "2023-01-24 11:00:00"
  And the fixtures files are loaded:
    | sylius_channels.yml |
    | payment_methods.yml |
    | products.yml        |
    | restaurants.yml     |
  And the restaurant with id "1" has products:
    | code      |
    | PIZZA     |
    | HAMBURGER |
  And the setting "brand_name" has value "CoopCycle"
  And the setting "default_tax_category" has value "tva_livraison"
  And the setting "subject_to_vat" has value "1"
  And the setting "stripe_test_secret_key" has value "sk_test_123"
  And the setting "stripe_test_publishable_key" has value "pk_1234567890"
  And the setting "stripe_test_connect_client_id" has value "ca_1234567890"
  Given stripe client is ready to use
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
      "shippedAt": "2023-01-24 12:30:00",
      "items": [{
        "product": "PIZZA",
        "quantity": 1,
        "options": [
          {"code": "PIZZA_TOPPING_PEPPERONI", "quantity": 1}
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
    "customer":@...@,
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
    "adjustments":@...@,
    "id":@integer@,
    "number":null,
    "total":@integer@,
    "itemsTotal":@integer@,
    "taxTotal":@integer@,
    "state":"cart",
    "notes": null,
    "createdAt":@string@,
    "shippedAt":"@string@.startsWith('2023-01-24T12:30:00')",
    "preparationExpectedAt":null,
    "pickupExpectedAt":null,
    "reusablePackagingEnabled": false
  }
  """
  Given the user "bob" is authenticated
  When I add "Content-Type" header equal to "application/ld+json"
  And I add "Accept" header equal to "application/ld+json"
  And the user "bob" sends a "GET" request to "/api/orders/1/stripe/clone-payment-method/pm_123456"
  Then the response status code should be 200
  And the response should be in JSON
  And the JSON should match:
      """
      {
        "@context":{"@*@":"@*@"},
        "@type":"StripePaymentMethodOutput",
        "@id":@string@,
        "id":"@string@.startsWith('pm_')"
      }
      """

Scenario: Stripe create setup intent
  Given the current time is "2023-01-24 11:00:00"
  And the fixtures files are loaded:
    | sylius_channels.yml |
    | payment_methods.yml |
    | products.yml        |
    | restaurants.yml     |
  And the restaurant with id "1" has products:
    | code      |
    | PIZZA     |
    | HAMBURGER |
  And the setting "brand_name" has value "CoopCycle"
  And the setting "default_tax_category" has value "tva_livraison"
  And the setting "subject_to_vat" has value "1"
  And the setting "stripe_test_secret_key" has value "sk_test_123"
  And the setting "stripe_test_publishable_key" has value "pk_1234567890"
  And the setting "stripe_test_connect_client_id" has value "ca_1234567890"
  Given stripe client is ready to use
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
      "shippedAt": "2023-01-24 12:30:00",
      "items": [{
        "product": "PIZZA",
        "quantity": 1,
        "options": [
          {"code": "PIZZA_TOPPING_PEPPERONI", "quantity": 1}
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
    "customer":@...@,
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
    "adjustments":@...@,
    "id":@integer@,
    "number":null,
    "total":@integer@,
    "itemsTotal":@integer@,
    "taxTotal":@integer@,
    "state":"cart",
    "notes": null,
    "createdAt":@string@,
    "shippedAt":"@string@.startsWith('2023-01-24T12:30:00')",
    "preparationExpectedAt":null,
    "pickupExpectedAt":null,
    "reusablePackagingEnabled": false
  }
  """
  Given the user "bob" is authenticated
  When I add "Content-Type" header equal to "application/ld+json"
  And I add "Accept" header equal to "application/ld+json"
  And the user "bob" sends a "POST" request to "/api/orders/1/stripe/create-setup-intent-or-attach-pm" with body:
    """
    {
      "payment_method_to_save": "pm_12345678"
    }
    """
  Then the response status code should be 201
