Feature: Checkout

  @javascript
  Scenario: Order something at restaurant
    Given the fixtures files are loaded:
      | sylius_channels.yml        |
      | restaurants_standalone.yml |
    And the user is loaded:
      | email     | bob@demo.coopcycle.org |
      | username  | bob                    |
      | password  | 123456                 |
      | telephone | +33612345678           |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "administrator_email" has value "admin@demo.coopcycle.org"
    Given I am on "/fr"
    And I click on restaurant "Crazy Hamburger"
    Then the url should match "/fr/restaurant/[0-9]+-crazy-hamburger"
    Given I wait for cart to be ready
    And I click on menu item "Cheeseburger"
    Then the product options modal should appear
    Given I check all the mandatory product options
    Then the product options modal submit button should not be disabled
    And I submit the product options modal
    Then the product "Cheeseburger" should be added to the cart
    And the address modal should appear
    Given I enter address "91 rue de rivoli" in the address modal
    Then I should see address suggestions in the address modal
    Given I select the first address suggestion in the address modal
    Then the address modal should disappear
    And the cart address picker should contain "91 Rue de Rivoli, Paris, France"
    Given I click on menu item "Cheese Cake"
    Then the product "Cheese Cake" should be added to the cart
    And the cart submit button should not be disabled
    Given I submit the cart
    Then I should be on "/login"
    Given I login with username "bob" and password "123456"
    Then I should be on "/order/"
    Given I press "Commander"
    Then I should be on "/order/payment"
    Given I enter test credit card details
    Then the url should match "/profile/orders/\d+"

  @javascript
  Scenario: Login and order something at restaurant with saved address
    Given the fixtures files are loaded:
      | sylius_channels.yml        |
      | restaurants_standalone.yml |
    And the user is loaded:
      | email     | bob@demo.coopcycle.org |
      | username  | bob                    |
      | password  | 123456                 |
      | telephone | +33612345678           |
    And the user "bob" has delivery address:
      | streetAddress | 1, Rue de Rivoli, Paris, France |
      | postalCode    | 75004                           |
      | geo           | 48.855799, 2.359207             |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "administrator_email" has value "admin@demo.coopcycle.org"
    Given I am on "/login"
    And I fill in "Email ou nom d'utilisateur" with "bob"
    And I fill in "Mot de passe" with "123456"
    And I press "Connexion"
    Then I should be on "/fr/"
    Given I enter address "1, Rue de Rivoli" in the homepage search
    Then I should see address suggestions in the homepage search
    And I should see section "Adresses sauvegardées" in the homepage search suggestions
    And I should see address "1, Rue de Rivoli, Paris, France" in section "Adresses sauvegardées" in the homepage search suggestions
    Given I select address "1, Rue de Rivoli, Paris, France" in section "Adresses sauvegardées" in the homepage search suggestions
    Then I should be on "/fr/restaurants"
    # TODO Assert query string contains "geohash" & "address"
    And I click on restaurant "Crazy Hamburger"
    Then the url should match "/fr/restaurant/[0-9]+-crazy-hamburger"
    Given I wait for cart to be ready
    Then the cart address picker should contain "1, Rue de Rivoli, Paris, France"
    Given I click on menu item "Cheeseburger"
    Then the product options modal should appear
    Given I check all the mandatory product options
    Then the product options modal submit button should not be disabled
    And I submit the product options modal
    Then the product "Cheeseburger" should be added to the cart
    Given I click on menu item "Cheese Cake"
    Then the product "Cheese Cake" should be added to the cart
    And the cart submit button should not be disabled
    Given I submit the cart
    Then I should be on "/order/"
    Given I press "Commander"
    Then I should be on "/order/payment"
    Given I enter test credit card details
    Then the url should match "/profile/orders/\d+"

  @javascript
  Scenario: Use search address as default
    Given the fixtures files are loaded:
      | sylius_channels.yml        |
      | restaurants_standalone.yml |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "administrator_email" has value "admin@demo.coopcycle.org"
    # TODO Make sure localStorage is empty
    Given I am on "/fr"
    Given I enter address "91 rue de rivoli" in the homepage search
    Then I should see address suggestions in the homepage search
    Given I select the first address suggestion in the homepage search
    And I wait for url to match "/fr/restaurants"
    Given I click on restaurant "Crazy Hamburger"
    Then the url should match "/fr/restaurant/[0-9]+-crazy-hamburger"
    And the cart address picker should contain "91 Rue de Rivoli, Paris, France"

  @javascript
  Scenario: Show warning when restaurant has changed
    Given the fixtures files are loaded:
      | sylius_channels.yml        |
      | restaurants_standalone.yml |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "administrator_email" has value "admin@demo.coopcycle.org"
    Given I am on "/fr"
    And I click on restaurant "Crazy Hamburger"
    Then the url should match "/fr/restaurant/[0-9]+-crazy-hamburger"
    Given I wait for cart to be ready
    And I click on menu item "Cheese Cake"
    Then the product "Cheese Cake" should be added to the cart
    Given I am on "/fr"
    And I click on restaurant "Pizza Express"
    Then the url should match "/fr/restaurant/[0-9]+-pizza-express"
    Given I wait for cart to be ready
    And I click on menu item "Pizza Margherita"
    Then the restaurant warning modal should appear
