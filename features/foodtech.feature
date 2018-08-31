Feature: Food Tech

  Scenario: Restaurant does not belong to user
    Given the database is empty
    And the fixtures file "restaurants.yml" is loaded
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "GET" request to "/api/restaurants/1/orders"
    Then the response status code should be 403

  Scenario: Retrieve restaurant orders
    Given the database is empty
    And the current time is "2018-08-27 12:00:00"
    And the fixtures file "products.yml" is loaded
    And the fixtures file "restaurants.yml" is loaded
    And the setting "default_tax_category" has value "tva_livraison"
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    Given the user "sarah" is loaded:
      | email      | sarah@coopcycle.org |
      | password   | 123456              |
    And the user "sarah" has ordered something for "2018-08-27 12:30:00" at restaurant with id "1"
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    And the restaurant with id "1" belongs to user "bob"
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "GET" request to "/api/restaurants/1/orders?date=2018-08-27"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/orders",
        "@type":"hydra:Collection",
        "hydra:member":@array@,
        "hydra:totalItems":1,
        "hydra:view":{
          "@id":"/api/restaurants/1/orders?date=2018-08-27",
          "@type":"hydra:PartialCollectionView"
        }
      }
      """

  Scenario: Refuse order with reason
    Given the database is empty
    And the fixtures file "products.yml" is loaded
    And the fixtures file "restaurants.yml" is loaded
    And the setting "default_tax_category" has value "tva_livraison"
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
    And the user "bob" is authenticated
    And the restaurant with id "1" belongs to user "bob"
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
    And the last order from "sarah" should be in state "refused"

  Scenario: Delay order
    Given the database is empty
    And the fixtures file "products.yml" is loaded
    And the fixtures file "restaurants.yml" is loaded
    And the setting "default_tax_category" has value "tva_livraison"
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
    And the user "bob" is authenticated
    And the restaurant with id "1" belongs to user "bob"
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/orders/1/delay" with body:
      """
      {
        "delay": 20
      }
      """
    Then the response status code should be 200
    # FIXME Assert that order has been delayed
