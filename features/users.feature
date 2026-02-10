Feature: Users

  Scenario: Not authorized to list users
    Given the fixtures files are loaded:
      | users.yml           |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/users?roles[]=ROLE_COURIER"
    Then the response status code should be 403

  Scenario: Not authorized to retrieve user
    Given the fixtures files are loaded:
      | users.yml           |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/users/2"
    Then the response status code should be 403

  Scenario: User can retrieve him/herself
    Given the fixtures files are loaded:
      | users.yml           |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/users/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/User",
        "@id":"/api/users/1",
        "@type":"User",
        "username":"bob",
        "email":"bob@demo.coopcycle.org",
        "givenName":null,
        "familyName":null,
        "telephone":null,
        "roles":[
          "ROLE_USER"
        ],
        "addresses":[]
      }
      """

  Scenario: Retrieve users filtered by role
    Given the fixtures files are loaded:
      | users.yml           |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "sarah" has role "ROLE_COURIER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/users?roles[]=ROLE_COURIER"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/User",
        "@id":"/api/users",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"@string@.startsWith('/api/users')",
            "@type":"User",
            "username":"sarah",
            "email":"sarah@demo.coopcycle.org",
            "givenName":null,
            "familyName":null,
            "telephone":null,
            "addresses":@array@,
            "roles":[
              "ROLE_COURIER",
              "ROLE_USER"
            ]
          }
        ],
        "hydra:totalItems":1,
        "hydra:view":{
          "@id":"/api/users?roles%5B%5D=ROLE_COURIER",
          "@type":"hydra:PartialCollectionView"
        },
        "hydra:search":{
          "@type":"hydra:IriTemplate",
          "hydra:template":"/api/users{?roles}",
          "hydra:variableRepresentation":"BasicRepresentation",
          "hydra:mapping":[
            {
              "@type":"IriTemplateMapping",
              "variable":"roles",
              "property":"roles",
              "required":false
            }
          ]
        }
      }
      """

  Scenario: User can update his/her telephone
    Given the fixtures files are loaded:
      | users.yml           |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | givenName  | John              |
      | familyName | Doe               |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/users/1" with body:
      """
      {
        "telephone": "+33612345678"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/User",
        "@id":"/api/users/1",
        "@type":"User",
        "username":"bob",
        "email":"bob@demo.coopcycle.org",
        "givenName":"John",
        "familyName":"Doe",
        "telephone":"+33612345678",
        "roles":[
          "ROLE_USER"
        ],
        "addresses":@array@
      }
      """

  # This is needed by the app
  # The field is named "telephone"
  # https://github.com/coopcycle/coopcycle-app/blob/33135cffd10e54c271b581a9f7ce063f4200fff1/src/redux/Checkout/actions.js#L677-L688
  Scenario: User can update the phone number associated to his/her customer account
    Given the fixtures files are loaded:
      | users.yml           |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | givenName  | John              |
      | familyName | Doe               |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/customers/1" with body:
      """
      {
        "telephone": "+33612345678"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Customer",
        "@id":"/api/customers/1",
        "@type":"Customer",
        "email":"bob@demo.coopcycle.org",
        "phoneNumber":"+33612345678",
        "telephone":"+33612345678",
        "username":"bob",
        "fullName":"John Doe"
      }
      """

  Scenario: Retrieve customer insights
    Given the current time is "2026-01-28 12:00:00"
    Given the fixtures files are loaded:
      | users.yml           |
      | payment_methods.yml |
      | products.yml        |
      | restaurants.yml     |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the restaurant with id "2" has products:
      | code      |
      | SALAD     |
      | CAKE      |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | givenName  | John              |
      | familyName | Doe               |
    And the user "bob" has role "ROLE_ADMIN"
    Given the user "bob" is authenticated
    And the user "bob" has ordered something for "2025-12-31 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-11-15 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-10-01 12:30:00" at the restaurant with id "2" and the order is fulfilled
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/customers/1/insights"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": {
          "@*@":"@*@"
        },
        "@type": "CustomerInsightsDto",
        "@id": @string@,
        "averageOrderTotal": @integer@,
        "firstOrderedAt": "@string@.isDateTime()",
        "lastOrderedAt": "@string@.isDateTime()",
        "numberOfOrders": 3,
        "favoriteRestaurant":"/api/restaurants/1"
      }
      """

  Scenario: Retrieve customer orders (with pagination)
    Given the current time is "2026-01-28 12:00:00"
    Given the fixtures files are loaded:
      | users.yml           |
      | payment_methods.yml |
      | products.yml        |
      | restaurants.yml     |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the restaurant with id "2" has products:
      | code      |
      | SALAD     |
      | CAKE      |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | givenName  | John              |
      | familyName | Doe               |
    And the user "bob" has role "ROLE_ADMIN"
    Given the user "bob" is authenticated
    # 36 orders, 30 per page -> 2 pages
    And the user "bob" has ordered something for "2025-12-31 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-11-15 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-10-01 12:30:00" at the restaurant with id "2" and the order is fulfilled
    And the user "bob" has ordered something for "2025-12-31 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-11-15 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-10-01 12:30:00" at the restaurant with id "2" and the order is fulfilled
    And the user "bob" has ordered something for "2025-12-31 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-11-15 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-10-01 12:30:00" at the restaurant with id "2" and the order is fulfilled
    And the user "bob" has ordered something for "2025-12-31 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-11-15 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-10-01 12:30:00" at the restaurant with id "2" and the order is fulfilled
    And the user "bob" has ordered something for "2025-12-31 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-11-15 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-10-01 12:30:00" at the restaurant with id "2" and the order is fulfilled
    And the user "bob" has ordered something for "2025-12-31 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-11-15 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-10-01 12:30:00" at the restaurant with id "2" and the order is fulfilled
    And the user "bob" has ordered something for "2025-12-31 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-11-15 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-10-01 12:30:00" at the restaurant with id "2" and the order is fulfilled
    And the user "bob" has ordered something for "2025-12-31 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-11-15 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-10-01 12:30:00" at the restaurant with id "2" and the order is fulfilled
    And the user "bob" has ordered something for "2025-12-31 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-11-15 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-10-01 12:30:00" at the restaurant with id "2" and the order is fulfilled
    And the user "bob" has ordered something for "2025-12-31 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-11-15 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-10-01 12:30:00" at the restaurant with id "2" and the order is fulfilled
    And the user "bob" has ordered something for "2025-12-31 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-11-15 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-10-01 12:30:00" at the restaurant with id "2" and the order is fulfilled
    And the user "bob" has ordered something for "2025-12-31 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-11-15 12:30:00" at the restaurant with id "1" and the order is fulfilled
    And the user "bob" has ordered something for "2025-10-01 12:30:00" at the restaurant with id "2" and the order is fulfilled
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/me/orders"
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/Order",
        "@id": "/api/me/orders",
        "@type": "hydra:Collection",
        "hydra:totalItems": 36,
        "hydra:member": [
          {
            "@id": "@string@.startsWith('/api/orders')",
            "total": @integer@,
            "items": @array@,
            "@*@": "@*@"
          },
          "@array_previous_repeat@"
        ],
        "hydra:view": {
          "@id": "/api/me/orders?page=1",
          "@type": "hydra:PartialCollectionView",
          "hydra:first": "/api/me/orders?page=1",
          "hydra:last": "/api/me/orders?page=2",
          "hydra:next": "/api/me/orders?page=2"
        },
        "hydra:search": {
          "@*@":"@*@"
        }
      }
      """
