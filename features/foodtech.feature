Feature: Food Tech

  Scenario: Restaurant does not belong to user
    Given the fixtures files are loaded:
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
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Restaurant",
        "@id":"/api/restaurants/1/orders",
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
              "tip":[],
              "incident":[]
            },
            "paymentMethod": "CARD",
            "hasReceipt":@boolean@,
            "invitation": "@string@||@null@",
            "events":@array@,
            "paymentGateway":@string@,
            "hasEdenredCredentials":@boolean@
          }
        ],
        "hydra:totalItems":1,
        "hydra:view":{
          "@id":"/api/restaurants/1/orders?date=2018-08-27",
          "@type":"hydra:PartialCollectionView"
        }
      }
      """

  Scenario: Refuse order with reason
    Given the fixtures files are loaded:
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
        "reusablePackagingQuantity": @integer@,
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
        "restaurant":{"@*@":"@*@"},
        "shippedAt":"2018-08-27T12:30:00+02:00",
        "preparationExpectedAt":"2018-08-27T12:05:00+02:00",
        "pickupExpectedAt":"2018-08-27T12:15:00+02:00",
        "adjustments":{"@*@": "@*@"},
        "events":@array@,
        "preparationTime":"@string@||@null@",
        "shippingTime":"@string@||@null@",
        "paymentMethod": "CARD",
        "hasReceipt":@boolean@,
        "invitation": "@string@||@null@",
        "paymentGateway":@string@,
        "hasEdenredCredentials":@boolean@,
        "assignedTo": "@string@||@null@"
      }
      """

  Scenario: Delay order
    Given the fixtures files are loaded:
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
        "customer":{"@*@":"@*@"},
        "restaurant":{
          "@id": "/api/restaurants/1",
          "@*@":"@*@"
        },
        "shippingAddress":{"@*@":"@*@"},
        "shippedAt":"@string@.isDateTime()",
        "reusablePackagingEnabled":false,
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
          "reusable_packaging":[],
          "tax":@array@,
          "tip":@array@,
          "incident":@array@
        },
        "shippingTimeRange": @array@,
        "takeaway":@boolean@,
        "vendor":{"@*@":"@*@"},
        "preparationTime":"@string@||@null@",
        "shippingTime":"@string@||@null@",
        "reusablePackagingEnabled":false,
        "reusablePackagingQuantity": @integer@,
        "paymentMethod": "CARD",
        "hasReceipt":@boolean@,
        "invitation": "@string@||@null@",
        "events":@array@,
        "paymentGateway":@string@,
        "hasEdenredCredentials":@boolean@,
        "assignedTo": "@string@||@null@"
      }
      """

  Scenario: Accept order when restaurant is closed
    Given the fixtures files are loaded:
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
        "customer":{"@*@":"@*@"},
        "restaurant":{
          "@id": "/api/restaurants/1",
          "@*@":"@*@"
        },
        "shippingAddress":{"@*@":"@*@"},
        "shippedAt":"@string@.isDateTime()",
        "reusablePackagingEnabled":false,
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
          "reusable_packaging":[],
          "tax":@array@,
          "tip":@array@,
          "incident":@array@
        },
        "shippingTimeRange": @array@,
        "takeaway":@boolean@,
        "vendor":{"@*@":"@*@"},
        "preparationTime":"@string@||@null@",
        "shippingTime":"@string@||@null@",
        "reusablePackagingEnabled":false,
        "reusablePackagingQuantity": @integer@,
        "paymentMethod": "CARD",
        "hasReceipt":@boolean@,
        "invitation": "@string@||@null@",
        "events":@array@,
        "paymentGateway":@string@,
        "hasEdenredCredentials":@boolean@,
        "assignedTo": "@string@||@null@"
      }
      """

  Scenario: Not authorized to accept order (with empty JSON payload)
    Given the fixtures files are loaded:
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
        "value":"Pepperoni",
        "name":@string@
      }
      """

  Scenario: Enable disabled product option value
    Given the fixtures files are loaded:
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
        "value":"Not enabled",
        "name":@string@
      }
      """

  Scenario: Not authorized to disable product
    Given the fixtures files are loaded:
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
        "@id":"/api/restaurants/1",
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
            "opens":"23:59",
            "closes":"00:00",
            "validFrom":"2020-10-02",
            "validThrough":"2020-10-02"
          }
        ],
        "image":@string@,
        "bannerImage":@string@,
        "isOpen":false,
        "nextOpeningDate":@string@,
        "hub":null,
        "loopeatEnabled":false,
        "tags":@array@,
        "badges":@array@,
        "autoAcceptOrdersEnabled": @boolean@,
        "edenredMerchantId": null,
        "edenredTRCardEnabled": false,
        "edenredSyncSent": false,
        "edenredEnabled": false
      }
      """

  Scenario: Close restaurant does not return past closingRules (specialOpeningHoursSpecification)
    Given the current time is "2020-10-02 11:00:00"
    Given the fixtures files are loaded:
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    # We create some expired closing rules
    And the restaurant with id "1" is closed between "2018-08-27 12:00:00" and "2018-08-28 10:00:00"
    And the restaurant with id "1" is closed between "2020-10-01 23:30:00" and "2020-10-01 23:59:59"
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
        "@id":"/api/restaurants/1",
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
            "opens":"23:59",
            "closes":"00:00",
            "validFrom":"2020-10-02",
            "validThrough":"2020-10-02"
          }
        ],
        "image":@string@,
        "bannerImage":@string@,
        "isOpen":false,
        "nextOpeningDate":@string@,
        "hub":null,
        "loopeatEnabled":false,
        "tags":@array@,
        "badges":@array@,
        "autoAcceptOrdersEnabled": @boolean@,
        "edenredMerchantId": null,
        "edenredTRCardEnabled": false,
        "edenredSyncSent": false,
        "edenredEnabled": false
      }
      """

  Scenario: Retrieve order with OAuth
    Given the current time is "2018-08-27 12:00:00"
    And the fixtures files are loaded:
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

  Scenario: Disable product until tomorrow
    Given the fixtures files are loaded:
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
    When the user "bob" sends a "PUT" request to "/api/products/1/disable" with body:
      """
      {
        "until": "tomorrow 00:00"
      }
      """
    Then the response status code should be 200

  Scenario: Create credit note
    Given the fixtures files are loaded:
      | payment_methods.yml |
      | products.yml        |
      | restaurants.yml     |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "sarah" is loaded:
      | email      | sarah@coopcycle.org |
      | password   | 123456              |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the user "sarah" has ordered something for "2026-03-11 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "POST" request to "/api/orders/1/credit_notes" with body:
      """
      {
        "amount": 250
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Promotion",
        "@id":"/api/promotions/1",
        "@type":"Promotion",
        "name":"Lorem ipsum",
        "coupons": [
          {
            "@type": "PromotionCoupon",
            "@id": @string@,
            "code": @string@,
            "used": 0,
            "updatedAt": "@string@.isDateTime()"
          }
        ]
      }
      """

  Scenario: Retrieve order payments & refund payment
    Given the fixtures files are loaded:
      | payment_methods.yml |
      | products.yml        |
      | restaurants.yml     |
    Given the setting "stripe_test_secret_key" has value "sk_test_123"
    And the setting "stripe_test_publishable_key" has value "pk_1234567890"
    Given stripe client is ready to use
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "sarah" is loaded:
      | email      | sarah@coopcycle.org |
      | password   | 123456              |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the user "sarah" has ordered something for "2026-03-11 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "GET" request to "/api/orders/1/payments"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/orders/1/payments",
        "@type":"hydra:Collection",
        "hydra:totalItems":1,
        "hydra:member":[
          {
            "@type":"Payment",
            "@id":"/api/payments/1",
            "method":{
              "@type":"PaymentMethod",
              "@id":@string@,
              "code":"CARD"
            },
            "amount":2150,
            "updatedAt":"@string@.isDateTime()",
            "supportsPartialRefunds": @boolean@,
            "refundedAmount":0
          }
        ],
        "hydra:search":{
          "@*@":"@*@"
        }
      }
      """
    Given I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "POST" request to "/api/payments/1/refunds" with body:
      """
      {
        "amount": 100,
        "liableParty": "merchant",
        "comments": "Missing fries"
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Payment",
        "@id":"/api/payments/1",
        "@type":"Payment",
        "method":{
          "@type":"PaymentMethod",
          "@id":@string@,
          "code":"CARD"
        },
        "amount":@integer@,
        "updatedAt":"@string@.isDateTime()",
        "supportsPartialRefunds": @boolean@,
        "refundedAmount":100
      }
      """

  Scenario: Refund an order
    Given the fixtures files are loaded:
      | payment_methods.yml |
      | products.yml        |
      | restaurants.yml     |
    Given the setting "stripe_test_secret_key" has value "sk_test_123"
    And the setting "stripe_test_publishable_key" has value "pk_1234567890"
    Given stripe client is ready to use
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "sarah" is loaded:
      | email      | sarah@coopcycle.org |
      | password   | 123456              |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the user "sarah" has ordered something for "2026-03-11 12:30:00" at the restaurant with id "1" and the order is fulfilled
    When I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/orders/1/refund" with body:
      """
      {
        "liableParty":"merchant",
        "comments":"Missing beer"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "GET" request to "/api/orders/1/refunds"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/Order",
        "@id": "/api/orders/1/refunds",
        "@type": "hydra:Collection",
        "hydra:totalItems": 1,
        "hydra:member": [
          {
            "@type": "Refund",
            "@id": @string@,
            "amount": 2150,
            "liableParty": "merchant",
            "comments": "Missing beer"
          }
        ],
        "hydra:search": {
          "@*@":"@*@"
        }
      }
      """
