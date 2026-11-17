Feature: Gift cards

  Scenario: Applying a gift card does not change what the order costs
    Given the fixtures files are loaded:
      | payment_methods.yml |
      | products.yml        |
      | restaurants.yml     |
      | gift_cards.yml      |
    And the setting "gift_cards_active" has value "1"
    And the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" is authenticated
    And the user "bob" has ordered something at the restaurant with id "1"
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/orders/1" with body:
      """
      {
        "giftCard": "ACTIVE234CARD"
      }
      """
    Then the response status code should be 200
    And the JSON node "giftCard" should be equal to "ACTIVE234CARD"
    And the JSON node "giftCardAmount" should exist
    # A gift card changes who pays, never what the order costs. That the
    # total is untouched is pinned precisely in
    # Tests\AppBundle\Sylius\OrderProcessing\OrderPaymentProcessorTest.
    And the JSON node "total" should exist

  Scenario: An unknown gift card code is refused with a reason
    Given the fixtures files are loaded:
      | payment_methods.yml |
      | products.yml        |
      | restaurants.yml     |
      | gift_cards.yml      |
    And the setting "gift_cards_active" has value "1"
    And the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" is authenticated
    And the user "bob" has ordered something at the restaurant with id "1"
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/orders/1" with body:
      """
      {
        "giftCard": "NOSUCHCODE23"
      }
      """
    Then the response status code should be 400

  Scenario: A gift card that has already been spent is refused
    Given the fixtures files are loaded:
      | payment_methods.yml |
      | products.yml        |
      | restaurants.yml     |
      | gift_cards.yml      |
    And the setting "gift_cards_active" has value "1"
    And the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" is authenticated
    And the user "bob" has ordered something at the restaurant with id "1"
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/orders/1" with body:
      """
      {
        "giftCard": "SPENT2345CARD"
      }
      """
    Then the response status code should be 400

  Scenario: An expired gift card is refused
    Given the fixtures files are loaded:
      | payment_methods.yml |
      | products.yml        |
      | restaurants.yml     |
      | gift_cards.yml      |
    And the setting "gift_cards_active" has value "1"
    And the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" is authenticated
    And the user "bob" has ordered something at the restaurant with id "1"
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When the user "bob" sends a "PUT" request to "/api/orders/1" with body:
      """
      {
        "giftCard": "OLDCARD23456"
      }
      """
    Then the response status code should be 400
