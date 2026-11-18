Feature: Gift cards

  Background:
    Given the fixtures files are loaded:
      | payment_methods.yml |
      | products.yml        |
      | restaurants.yml     |
      | gift_cards.yml      |
    And the setting "default_tax_category" has value "tva_livraison"
    And the setting "subject_to_vat" has value "1"
    And the setting "gift_cards_active" has value "1"
    And the restaurant with id "1" has products:
      | code      |
      | PIZZA     |
      | HAMBURGER |
    And the user "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" has created a cart at restaurant with id "1"
    And the user "bob" is authenticated
    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/orders/1/items" with body:
      """
      {
        "product": "PIZZA",
        "quantity": 2
      }
      """
    Then the response status code should be 201

  # The invariant the whole feature rests on. A gift card changes who pays,
  # never what the order costs: the restaurant's share and every tax
  # adjustment have to come out exactly as they would without it.
  Scenario: Applying a gift card does not change what the order costs
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/orders/1" with body:
      """
      {
        "giftCard": "ACTIVE234CARD"
      }
      """
    Then the response status code should be 200
    And the JSON node "giftCard" should be equal to "ACTIVE234CARD"
    And the JSON node "total" should be equal to the number 2150
    # The card covers the lot, so there is nothing left for a card to pay.
    And the JSON node "giftCardAmount" should be equal to the number 2150

  # A card worth less than the order pays what it can, and the rest is still
  # paid however the customer likes.
  Scenario: A card smaller than the order is split with a card payment
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/orders/1" with body:
      """
      {
        "giftCard": "SMALL2345CARD"
      }
      """
    Then the response status code should be 200
    And the JSON node "total" should be equal to the number 2150
    And the JSON node "giftCardAmount" should be equal to the number 500

  Scenario: Taking the gift card back off restores an ordinary order
    Given I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/orders/1" with body:
      """
      {
        "giftCard": "ACTIVE234CARD"
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/orders/1" with body:
      """
      {
        "giftCard": ""
      }
      """
    Then the response status code should be 200
    And the JSON node "giftCard" should not exist
    And the JSON node "total" should be equal to the number 2150

  Scenario: An unknown gift card code is refused
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/orders/1" with body:
      """
      {
        "giftCard": "NOSUCHCODE23"
      }
      """
    Then the response status code should be 400

  Scenario: A gift card that has already been spent is refused
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/orders/1" with body:
      """
      {
        "giftCard": "SPENT2345CARD"
      }
      """
    Then the response status code should be 400

  Scenario: An expired gift card is refused
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/orders/1" with body:
      """
      {
        "giftCard": "OLDCARD23456"
      }
      """
    Then the response status code should be 400
