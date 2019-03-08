Feature: Login Test

  @javascript
  Scenario: The login page is displayed
    Given I am on "/fr"
    When I follow "Connexion"
    Then I should see "Email ou nom d'utilisateur"
    And I should see "Mot de passe"

  @javascript
  Scenario: login failed
    When I fill in "Email ou nom d'utilisateur" with "bob"
    And I fill in "Mot de passe" with "123"
    And I press "Connexion"
    Then I should see "Identifiants invalides."

  @javascript
  Scenario: login successfully
    Given the user is loaded:
      | email    | bob@coopcycle.org |
      | username | bob               |
      | password | 123456            |
    When I fill in "Email ou nom d'utilisateur" with "bob"
    And I fill in "Mot de passe" with "123456"
    And I press "Connexion"
    Then I should not see "Identifiants invalides."
    And I should see "bob"
