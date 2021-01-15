Feature: Maintenance

  Scenario: Cannot order when platform is in maintenance
    Given the current time is "2017-09-02 11:00:00"
    And the maintenance mode is on
    And the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the setting "brand_name" has value "CoopCycle"
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
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
    # This is the User-Agent used by the app
    And I add "User-Agent" header equal to "okhttp/3.12.1"
    And the user "bob" sends a "POST" request to "/api/orders" with body:
      """
      {
        "restaurant": "/api/restaurants/1",
        "shippingAddress": "/api/addresses/4",
        "shippedAt": "2017-09-02 12:30:00",
        "items": [{
          "product": "PIZZA",
          "quantity": 1,
          "options": [
            "PIZZA_TOPPING_PEPPERONI"
          ]
        }, {
          "product": "HAMBURGER",
          "quantity": 2
        }]
      }
      """
    Then the response status code should be 503
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "message":@string@
      }
      """

  Scenario: Retrieve assigned tasks (maintenance enabled)
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    And the maintenance mode is on
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/me/tasks/2018-03-02"
    Then the response status code should be 200
