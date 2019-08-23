context('Checkout', () => {
  beforeEach(() => {

    const prefix = Cypress.env('COMMAND_PREFIX')

    let cmd = 'bin/console coopcycle:fixtures:load -f cypress/fixtures/checkout.yml --env test'
    if (prefix) {
      cmd = `${prefix} ${cmd}`
    }

    cy.exec(cmd)
  })

  it('order something at restaurant', () => {

    cy.server()
    cy.route('POST', '/fr/restaurant/*-crazy-hamburger').as('postRestaurant')
    cy.route('POST', '/fr/restaurant/*/cart/product/*').as('postProduct')

    cy.visit('/fr/')

    cy.contains('Crazy Hamburger').click()

    cy.wait('@postRestaurant')

    cy.location('pathname').should('match', /\/fr\/restaurant\/[0-9]+-crazy-hamburger/)

    cy.contains('Cheeseburger').click()

    cy.get('#CHEESEBURGER-options')
        .should('be.visible')

    // Make sure to use a precise selector, because 2 products have same options
    cy.get('#CHEESEBURGER-options input[name="options[HAMBURGER_ACCOMPANIMENT]"]')
        .check('HAMBURGER_ACCOMPANIMENT_FRENCH_FRIES')
    cy.get('#CHEESEBURGER-options input[name="options[HAMBURGER_DRINK]"]')
        .check('HAMBURGER_DRINK_COLA')

    // FIXME We need to use force = true, because the button has disabled=""
    cy.get('#CHEESEBURGER-options button[type="submit"]').click({ timeout: 5000, force: true })

    cy.wait('@postProduct')

    cy.get('.cart__items').invoke('text').should('match', /Cheeseburger/)

    cy.get('.ReactModal__Content--enter-address')
        .should('be.visible')

    cy.get('.ReactModal__Content--enter-address input[type="search"]')
        .type('91 rue de rivoli paris', { timeout: 5000 })

    cy.contains('91 Rue de Rivoli, Paris, France').click()

    cy.wait('@postRestaurant')

    cy.get('.cart .address-autosuggest__container input[type="search"]')
        .should('have.value', '91 Rue de Rivoli, Paris, France')

    cy.contains('Cheese Cake').click()

    cy.wait('@postProduct')

    cy.get('.cart__items').invoke('text').should('match', /Cheese Cake/)

    cy.get('form[name="cart"]').submit()

    cy.location('pathname').should('eq', '/login')

    cy.get('[name="_username"]').type('bob')
    cy.get('[name="_password"]').type('12345678')
    cy.get('[name="_submit"]').click()

    cy.location('pathname').should('eq', '/order/')

    cy.contains('Commander').click()

    cy.location('pathname').should('eq', '/order/payment')

    const expDate = Cypress.moment().add(6, 'month').format('MMYY')

    // @see https://github.com/cypress-io/cypress/issues/136
    cy.get('.StripeElement iframe')
        .then(function ($iframe) {

            const $body = $iframe.contents().find('body')

            cy
              .wrap($body)
              .find('input[name="cardnumber"]')
              .type('4242424242424242')

            cy
              .wrap($body)
              .find('input[name="exp-date"]')
              .type(expDate)

            cy
              .wrap($body)
              .find('input[name="cvc"]')
              .type('123')
        })

    cy.get('form[name="checkout_payment"]').submit()

    cy.location('pathname').should('match', /\/profile\/orders\/[0-9]+/)

  })
})
