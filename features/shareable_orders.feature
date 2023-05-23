Feature: Group orders

  Scenario: Create invitation link for order
    Given the current time is "2023-01-25 11:00:00"
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
    And the user "bob" has ordered something at the restaurant with id "1"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/orders/1/create_invitation" with body:
      """
      {}
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
        {
          "invitation":@string@,
          "@*@":"@*@"
        }
      """
