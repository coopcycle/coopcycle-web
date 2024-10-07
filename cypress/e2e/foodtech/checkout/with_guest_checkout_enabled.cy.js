describe('Checkout (happy path); with guest checkout enabled', () => {
  beforeEach(() => {

    cy.symfonyConsole(
      'coopcycle:fixtures:load -f cypress/fixtures/checkout.yml')

    cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart')
    cy.intercept('POST', '/fr/restaurant/*/cart/product/*').as('postProduct')

  })

  beforeEach(() => {
    cy.symfonyConsole(
      'craue:setting:create --section="general" --name="guest_checkout_enabled" --value="1" --force')
  })

  it('order something at restaurant (guest)', () => {

    // cy.intercept('POST', '/order/').as('postOrder')

    cy.visit('/fr/')

    cy.contains('Crazy Hamburger').click()

    cy.location('pathname')
      .should('match', /\/fr\/restaurant\/[0-9]+-crazy-hamburger/)

    cy.wait('@postRestaurantCart')

    cy.addProduct('Cheeseburger', '#CHEESEBURGER_crazy_hamburger-options', 2, [
      'HAMBURGER_ACCOMPANIMENT_FRENCH_FRIES_crazy_hamburger',
      'HAMBURGER_DRINK_COLA_crazy_hamburger' ])

    cy.wait('@postProduct', { timeout: 5000 })

    cy.get('.cart__items').invoke('text').should('match', /Cheeseburger/)

    cy.searchAddressUsingAddressModal(
      '.ReactModal__Content--enter-address',
      '91 rue de rivoli paris',
      /^91,? Rue de Rivoli,? 75001,? Paris,? France/i,
    )

    cy.wait('@postRestaurantCart')

    cy.get(
      '#restaurant__fulfilment-details__container [data-testid="cart.shippingAddress"]')
      .invoke('text')
      .should('match', /^91,? Rue de Rivoli,? 75001,? Paris,? France/i)

    cy.get('form[name="cart"]').submit()

    cy.location('pathname').should('eq', '/order/')

    //TODO; test adding tips separately
    // fails on github CI
    // cy.get('.table-order-items tfoot tr:last-child td')
    //   .invoke('text')
    //   .invoke('trim')
    //   .should('equal', "20,00 €")

    // cy.get('#tip-incr').click()
    // cy.wait('@postOrder')
    //
    // cy.get('.loadingoverlay', { timeout: 15000 }).should('not.exist')

    // fails on github CI
    //         cy.get('.table-order-items tfoot tr:last-child td')
    //           .invoke('text')
    //           .invoke('trim')
    //           .should('equal', "21,00 €")

    cy.get('input[name="checkout_address[customer][email]"]')
      .type('e2e-web@demo.coopcycle.org')

    cy.get('input[name="checkout_address[customer][phoneNumber]"]')
      .type('+33612345678')

    cy.get('input[name="checkout_address[customer][fullName]"]')
      .type('John Doe')

    cy.get('input[name="checkout_address[customer][legal]"]')
      .check()

    cy.contains('Commander').click()

    cy.location('pathname').should('eq', '/order/payment')

    cy.get('form[name="checkout_payment"] input[type="text"]')
      .type('John Doe')
    cy.enterCreditCard()

    cy.get('form[name="checkout_payment"]').submit()

    cy.location('pathname', { timeout: 30000 })
      .should('match', /\/order\/confirm\/[a-zA-Z0-9]+/)

    cy.get('#order-timeline').contains('Commande en attente de validation')

  })
})
