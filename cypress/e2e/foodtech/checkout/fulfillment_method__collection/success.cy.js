describe('Checkout; non business context; logged in customer; fulfilment method: collection: happy path', () => {
  beforeEach(() => {
    cy.symfonyConsole(
      'coopcycle:fixtures:load -f cypress/fixtures/checkout.yml',
    )

    cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart')
    cy.intercept('POST', '/fr/restaurant/*/cart/product/*').as('postProduct')
  })

  it('order something at restaurant', () => {
    cy.visit('/login')

    cy.login('bob', '12345678')

    cy.visit('/fr/')

    cy.clickRestaurant(
      'Restaurant with collection',
      /\/fr\/restaurant\/[0-9]+-restaurant-with-collection/,
    )

    cy.wait('@postRestaurantCart')

    cy.addProduct(
      'Cheeseburger',
      '#CHEESEBURGER_restaurant_with_collection-options',
      1,
      [
        'HAMBURGER_ACCOMPANIMENT_FRENCH_FRIES_restaurant_with_collection',
        'HAMBURGER_DRINK_COLA_restaurant_with_collection',
      ],
    )

    cy.wait('@postProduct', { timeout: 5000 })

    cy.get('.cart__items')
      .invoke('text')
      .should('match', /Cheeseburger/)

    cy.get('.ReactModal__Content--enter-address').should('be.visible')

    cy.get(
      `.ReactModal__Content--enter-address [data-testid="fulfilment_method.collection"]`,
    )
      .eq(1)
      .click()

    cy.wait('@postRestaurantCart')

    cy.addProduct(
      'Cheese Cake',
      '#CHEESECAKE_restaurant_with_collection-options',
    )

    cy.wait('@postProduct', { timeout: 5000 })

    cy.get('.cart__items', { timeout: 10000 })
      .invoke('text')
      .should('match', /Cheese Cake/)

    cy.get('form[name="cart"]').submit()

    cy.location('pathname').should('eq', '/order/')

    cy.get('input[name="checkout_address[customer][fullName]"]').type(
      'John Doe',
    )

    cy.contains('Commander').click()

    cy.location('pathname').should('eq', '/order/payment')

    cy.get('form[name="checkout_payment"] input[type="text"]').type('John Doe')
    cy.enterCreditCard()

    cy.get('form[name="checkout_payment"]').submit()

    cy.location('pathname', { timeout: 30000 }).should(
      'match',
      /\/order\/confirm\/[a-zA-Z0-9]+/,
    )

    cy.get('#order-timeline').contains('Commande en attente de validation')
  })
})
