Feature: Food Tech

  Scenario: Restaurant does not belong to user
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    And the user "bob" has role "ROLE_RESTAURANT"
    And the restaurant with id "2" belongs to user "bob"
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "GET" request to "/api/restaurants/1/orders"
    Then the response status code should be 403

  Scenario: Retrieve restaurant orders
    Given the current time is "2018-08-27 12:00:00"
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | payment_methods.yml |
      | products.yml        |
      | restaurants.yml     |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    Given the user "sarah" is loaded:
      | email      | sarah@coopcycle.org |
      | password   | 123456              |
    And the user "sarah" has ordered something for "2018-08-27 12:30:00" at the restaurant with id "1"
    And the user "sarah" has ordered something for "2018-08-28 12:30:00" at the restaurant with id "1"
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_RESTAURANT"
    And the restaurant with id "1" belongs to user "bob"
    And the user "bob" is authenticated
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "GET" request to "/api/restaurants/1/orders?date=2018-08-27"
    Then the response status code should be 200
    And the response should be in JSON
    # FIXME @id should be "/api/restaurants/1/orders"
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":@string@,
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/orders/1",
            "@type":"http://schema.org/Order",
            "customer":{"@*@":"@*@"},
            "vendor":{"@*@":"@*@"},
            "restaurant":{"@*@":"@*@"},
            "shippingAddress":{"@*@":"@*@"},
            "shippedAt":"@string@.isDateTime()",
            "reusablePackagingEnabled":false,
            "reusablePackagingPledgeReturn":0,
            "reusablePackagingQuantity": @integer@,
            "shippingTimeRange":@array@,
            "takeaway":false,
            "id":@integer@,
            "number":null,
            "notes":null,
            "items":@array@,
            "itemsTotal":1800,
            "total":2150,
            "state":"new",
            "createdAt":"@string@.isDateTime()",
            "taxTotal":@integer@,
            "preparationExpectedAt":"@string@.isDateTime()",
            "pickupExpectedAt":"@string@.isDateTime()",
            "assignedTo": "@string@||@null@",
            "preparationTime":"@string@||@null@",
            "shippingTime":"@string@||@null@",
            "adjustments":{
              "delivery":@array@,
              "delivery_promotion":[],
              "order_promotion":[],
              "reusable_packaging":[],
              "tax":@array@,
              "tip":[]
            },
            "paymentMethod": "CARD",
            "hasReceipt":@boolean@,
            "invitation": "@string@||@null@",
            "events":@array@
          }
        ],
        "hydra:totalItems":1,
        "hydra:view":{
          "@id":"/api/restaurants/1/orders?date=2018-08-27",
          "@type":"hydra:PartialCollectionView"
        },
        "hydra:search":{
          "@type":"hydra:IriTemplate",
          "hydra:template":"/api/restaurants/1/orders{?date}",
          "hydra:variableRepresentation":"BasicRepresentation",
          "hydra:mapping":[
            {
              "@type":"IriTemplateMapping",
              "variable":"date",
              "property":"date",
              "required":false
            }
          ]
        }
      }
      """

  Scenario: Refuse order with reason
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | payment_methods.yml |
      | products.yml        |
      | restaurants.yml     |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    # FIXME This is needed for email notifications. It should be defined once.
    And the setting "administrator_email" has value "admin@coopcycle.org"
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    Given the user "sarah" is loaded:
      | email      | sarah@coopcycle.org |
      | password   | 123456              |
    And the user "sarah" has ordered something for "2018-08-27 12:30:00" at the restaurant with id "1"
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_RESTAURANT"
    And the restaurant with id "1" belongs to user "bob"
    And the user "bob" is authenticated
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/orders/1/refuse" with body:
      """
      {
        "reason": "Restaurant is closing"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":@string@,
        "@type":"http://schema.org/Order",
        "customer":{"@*@":"@*@"},
        "vendor":{"@*@":"@*@"},
        "shippingAddress":{"@*@":"@*@"},
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn":0,
        "shippingTimeRange":[
          "2018-08-27T12:25:00+02:00",
          "2018-08-27T12:35:00+02:00"
        ],
        "takeaway":false,
        "id":@integer@,
        "number":null,
        "notes":null,
        "items":@array@,
        "itemsTotal":1800,
        "total":2150,
        "state":"refused",
        "createdAt":"@string@.isDateTime()",
        "taxTotal":222,
        "restaurant":@...@,
        "shippedAt":"2018-08-27T12:30:00+02:00",
        "preparationExpectedAt":"2018-08-27T12:25:00+02:00",
        "pickupExpectedAt":"2018-08-27T12:35:00+02:00",
        "adjustments":{"@*@": "@*@"},
        "events":@array@
      }
      """

  Scenario: Delay order
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | payment_methods.yml |
      | products.yml        |
      | restaurants.yml     |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    # FIXME This is needed for email notifications. It should be defined once.
    And the setting "administrator_email" has value "admin@coopcycle.org"
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    Given the user "sarah" is loaded:
      | email      | sarah@coopcycle.org |
      | password   | 123456              |
    And the user "sarah" has ordered something for "2018-08-27 12:30:00" at the restaurant with id "1"
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_RESTAURANT"
    And the restaurant with id "1" belongs to user "bob"
    And the user "bob" is authenticated
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/orders/1/delay" with body:
      """
      {
        "delay": 20
      }
      """
    Then the response status code should be 200

  Scenario: Not authorized to delay order
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    # FIXME This is needed for email notifications. It should be defined once.
    And the setting "administrator_email" has value "admin@coopcycle.org"
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    Given the user "sarah" is loaded:
      | email      | sarah@coopcycle.org |
      | password   | 123456              |
    And the user "sarah" has ordered something for "2018-08-27 12:30:00" at the restaurant with id "1"
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_RESTAURANT"
    And the restaurant with id "2" belongs to user "bob"
    And the user "bob" is authenticated
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/orders/1/delay" with body:
      """
      {
        "delay": 20
      }
      """
    Then the response status code should be 403
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Error",
        "@type":"hydra:Error",
        "hydra:title":"An error occurred",
        "hydra:description":"Access Denied.",
        "trace":@array@
      }
      """

  Scenario: Accept order (with empty JSON payload)
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | payment_methods.yml |
      | products.yml        |
      | restaurants.yml     |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    # FIXME This is needed for email notifications. It should be defined once.
    And the setting "administrator_email" has value "admin@coopcycle.org"
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    Given the user "sarah" is loaded:
      | email      | sarah@coopcycle.org |
      | password   | 123456              |
    And the user "sarah" has ordered something for "2018-08-27 12:30:00" at the restaurant with id "1"
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_RESTAURANT"
    And the restaurant with id "1" belongs to user "bob"
    And the user "bob" is authenticated
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/orders/1/accept"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/orders/1",
        "@type":"http://schema.org/Order",
        "customer":@...@,
        "restaurant":@...@,
        "shippingAddress":@...@,
        "shippedAt":"@string@.isDateTime()",
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn": 0,
        "id":1,
        "number":null,
        "notes":null,
        "items":@array@,
        "itemsTotal":1800,
        "total":2150,
        "state":"accepted",
        "createdAt":"@string@.isDateTime()",
        "taxTotal":222,
        "preparationExpectedAt":"@string@.isDateTime()",
        "pickupExpectedAt":"@string@.isDateTime()",
        "adjustments":{
          "delivery":@array@,
          "delivery_promotion":[],
          "order_promotion":[],
          "reusable_packaging":[]
        }
      }
      """

  Scenario: Accept order when restaurant is closed
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | payment_methods.yml |
      | products.yml        |
      | restaurants.yml     |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    # FIXME This is needed for email notifications. It should be defined once.
    And the setting "administrator_email" has value "admin@coopcycle.org"
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the restaurant with id "1" is closed between "2018-08-27 12:00:00" and "2018-08-28 10:00:00"
    Given the user "sarah" is loaded:
      | email      | sarah@coopcycle.org |
      | password   | 123456              |
    And the user "sarah" has ordered something for "2018-08-27 12:30:00" at the restaurant with id "1"
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_RESTAURANT"
    And the restaurant with id "1" belongs to user "bob"
    And the user "bob" is authenticated
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/orders/1/accept"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/orders/1",
        "@type":"http://schema.org/Order",
        "customer":@...@,
        "restaurant":@...@,
        "shippingAddress":@...@,
        "shippedAt":"@string@.isDateTime()",
        "reusablePackagingEnabled":false,
        "reusablePackagingPledgeReturn": 0,
        "id":1,
        "number":null,
        "notes":null,
        "items":@array@,
        "itemsTotal":1800,
        "total":2150,
        "state":"accepted",
        "createdAt":"@string@.isDateTime()",
        "taxTotal":222,
        "preparationExpectedAt":"@string@.isDateTime()",
        "pickupExpectedAt":"@string@.isDateTime()",
        "adjustments":{
          "delivery":@array@,
          "delivery_promotion":[],
          "order_promotion":[],
          "reusable_packaging":[]
        }
      }
      """

  Scenario: Not authorized to accept order (with empty JSON payload)
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    # FIXME This is needed for email notifications. It should be defined once.
    And the setting "administrator_email" has value "admin@coopcycle.org"
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    Given the user "sarah" is loaded:
      | email      | sarah@coopcycle.org |
      | password   | 123456              |
    And the user "sarah" has ordered something for "2018-08-27 12:30:00" at the restaurant with id "1"
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_RESTAURANT"
    And the restaurant with id "2" belongs to user "bob"
    And the user "bob" is authenticated
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/orders/1/accept"
    Then the response status code should be 403
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Error",
        "@type":"hydra:Error",
        "hydra:title":"An error occurred",
        "hydra:description":"Access Denied.",
        "trace":@array@
      }
      """

  Scenario: Disable product
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_RESTAURANT"
    And the restaurant with id "1" belongs to user "bob"
    And the user "bob" is authenticated
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/products/1" with body:
      """
      {
        "enabled": false
      }
      """
    Then the response status code should be 200

  Scenario: Disable product option value
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_RESTAURANT"
    And the restaurant with id "1" belongs to user "bob"
    And the user "bob" is authenticated
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/product_option_values/1" with body:
      """
      {
        "enabled": false
      }
      """
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ProductOptionValue",
        "@id":"/api/product_option_values/1",
        "@type":"ProductOptionValue",
        "price":0,
        "code":"PIZZA_TOPPING_PEPPERONI",
        "enabled":false,
        "value":"Pepperoni"
      }
      """

  Scenario: Enable disabled product option value
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_RESTAURANT"
    And the restaurant with id "1" belongs to user "bob"
    And the user "bob" is authenticated
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/product_option_values/3" with body:
      """
      {
        "enabled": true
      }
      """
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ProductOptionValue",
        "@id":"/api/product_option_values/3",
        "@type":"ProductOptionValue",
        "price":0,
        "code":"NOT_ENABLED_OPTION",
        "enabled":true,
        "value":"Not enabled"
      }
      """

  Scenario: Not authorized to disable product
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_RESTAURANT"
    And the restaurant with id "1" belongs to user "bob"
    And the user "bob" is authenticated
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/products/3" with body:
      """
      {
        "enabled": false
      }
      """
    Then the response status code should be 403

  Scenario: Close restaurant
    Given the current time is "2020-10-02 11:00:00"
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_RESTAURANT"
    And the restaurant with id "1" belongs to user "bob"
    And the user "bob" is authenticated
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/restaurants/1/close" with body:
      """
      {}
      """
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Restaurant",
        "@id":@string@,
        "@type":"http://schema.org/Restaurant",
        "id":@integer@,
        "name":"Nodaiwa",
        "description":null,
        "enabled":true,
        "depositRefundEnabled":false,
        "depositRefundOptin":true,
        "address":{"@*@":"@*@"},
        "state":"normal",
        "telephone":"+33612345678",
        "fulfillmentMethods":@array@,
        "openingHoursSpecification":@array@,
        "specialOpeningHoursSpecification":[
          {
            "@id":@string@,
            "@type":"OpeningHoursSpecification",
            "id":@integer@,
            "opens":"00:00",
            "closes":"00:00",
            "validFrom":"2020-10-02",
            "validThrough":"2020-10-03"
          }
        ],
        "image":@string@,
        "isOpen":false,
        "nextOpeningDate":@string@,
        "hub":null,
        "loopeatEnabled":false
      }
      """

  Scenario: Retrieve order with OAuth
    Given the current time is "2018-08-27 12:00:00"
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | payment_methods.yml |
      | products.yml        |
      | restaurants.yml     |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    Given the user "sarah" is loaded:
      | email      | sarah@coopcycle.org |
      | password   | 123456              |
    And the user "sarah" has ordered something for "2018-08-27 12:30:00" at the restaurant with id "1"
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the restaurant with name "Nodaiwa" has an OAuth client named "Nodaiwa"
    And the OAuth client with name "Nodaiwa" has an access token
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the OAuth client "Nodaiwa" sends a "GET" request to "/api/orders/1"
    Then the response status code should be 200
    And the response should be in JSON

  Scenario: Accept order with OAuth
    Given the current time is "2018-08-27 12:00:00"
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | payment_methods.yml |
      | products.yml        |
      | restaurants.yml     |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    # FIXME This is needed for email notifications. It should be defined once.
    And the setting "administrator_email" has value "admin@coopcycle.org"
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    Given the user "sarah" is loaded:
      | email      | sarah@coopcycle.org |
      | password   | 123456              |
    And the user "sarah" has ordered something for "2018-08-27 12:30:00" at the restaurant with id "1"
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the restaurant with name "Nodaiwa" has an OAuth client named "Nodaiwa"
    And the OAuth client with name "Nodaiwa" has an access token
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the OAuth client "Nodaiwa" sends a "PUT" request to "/api/orders/1/accept" with body:
      """
      {}
      """
    Then the response status code should be 200
    And the response should be in JSON
