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
    Given I enter address "91 rue de rivoli" in the address modal
    Then I should see address suggestions in the address modal
    Given I select the first address suggestion in the address modal
    Then the address modal should disappear
    And the cart address picker should contain "91 Rue de Rivoli, Paris, France"
