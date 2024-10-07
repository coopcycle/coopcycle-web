describe('Checkout (happy path); (business context)', () => {
  beforeEach(() => {
    cy.symfonyConsole(
      'coopcycle:fixtures:load -f cypress/fixtures/checkout_platform_catering.yml',
    )

    cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart')
    cy.intercept('POST', '/fr/restaurant/*/cart/product/*').as('postProduct')
  })

  it('order something at restaurant', () => {
    cy.visit('/login')
    cy.login('employee', '12345678')

    cy.visit('/fr/?_business=true')

    cy.clickRestaurant(
      'Crazy Hamburger',
      /\/fr\/restaurant\/[0-9]+-crazy-hamburger/,
    )

    // Office address
    cy.get(
      '#restaurant__fulfilment-details__container [data-testid="cart.shippingAddress"]',
    )
      .invoke('text')
      .should('match', /^272,? rue Saint Honoré,? 75001,? Paris,? 1er/i)

    cy.addProduct(
      'Sandwich Lunch Special',
      '#SANDWICH_LUNCH_SPECIAL-options',
      1,
    )

    cy.wait('@postProduct', { timeout: 5000 })

    cy.get('.cart__items')
      .invoke('text')
      .should('match', /Sandwich Lunch Special/)

    cy.get('form[name="cart"]').submit()

    cy.location('pathname').should('eq', '/order/')

    cy.get('input[name="checkout_address[customer][fullName]"]').type(
      'John Doe',
    )

    cy.get('button[type="submit"]').contains('Commander').click()

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
