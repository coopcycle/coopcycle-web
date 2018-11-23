Feature: Checkout

  @javascript
  Scenario: Order something at restaurant
    Given the fixtures files are loaded:
      | restaurants_standalone.yml |
    And the setting "default_tax_category" has value "tva_livraison"
    Given I am on "/fr"
    And I click on restaurant "Crazy Hamburger"
    And I click on menu item "Cheeseburger"
    Then the product options modal should appear
    Given I check all the mandatory product options
    Then the product options modal submit button should not be disabled
    And I submit the product options modal
    Then a product should be added to the cart
    And the address modal should appear
