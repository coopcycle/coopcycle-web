describe('Failed checkout; restaurant is closed', () => {
  beforeEach(() => {

    cy.symfonyConsole(
      'coopcycle:fixtures:load -f cypress/fixtures/checkout.yml')

    cy.window().then((win) => {
      win.sessionStorage.clear()
    })

    cy.visit('/login')

    cy.login('bob', '12345678')
  })

  context('restaurant is closed while the customer is on the menu page', () => {
    it('proceed with the checkout (FIXME)', () => {

      cy.visit('/fr/')

      cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart1')

      cy.clickRestaurant(
        'Crazy Hamburger',
        /\/fr\/restaurant\/[0-9]+-crazy-hamburger/,
      )

      cy.wait('@postRestaurantCart1')

      cy.intercept('POST', '/fr/restaurant/*/cart/product/*').as('postProduct1')

      cy.addProduct('Cheeseburger', '#CHEESEBURGER-options', 2, [
        'HAMBURGER_ACCOMPANIMENT_FRENCH_FRIES',
        'HAMBURGER_DRINK_COLA' ])

      cy.wait('@postProduct1', { timeout: 5000 })

      cy.get('.cart__items').invoke('text').should('match', /Cheeseburger/)

      cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart2')

      cy.searchAddress(
        '.ReactModal__Content--enter-address',
        '91 rue de rivoli paris',
        /^91,? Rue de Rivoli,? 75001,? Paris,? France/i,
      )

      cy.wait('@postRestaurantCart2')

      cy.get(
        '#restaurant__fulfilment-details__container [data-testid="cart.shippingAddress"]')
        .invoke('text')
        .should('match', /^91,? Rue de Rivoli,? 75001,? Paris,? France/i)

      cy.closeRestaurant('resto_1', 'resto_1')

      cy.get('.order-button:visible').click()

      //FIXME: this behaviour should be changed
      // and the customer should be warned that the order is going to be placed for the next day
      // see https://github.com/coopcycle/coopcycle-web/issues/4167
      cy.location('pathname').should('eq', '/order/')
    })
  })

  context('restaurant is closed while the customer is on the address page',
    () => {
      it('show an error message (address page)', () => {

        cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart')
        cy.intercept('POST', '/fr/restaurant/*/cart/product/*')
          .as('postProduct')

        cy.visit('/fr/')

        cy.clickRestaurant(
          'Crazy Hamburger',
          /\/fr\/restaurant\/[0-9]+-crazy-hamburger/,
        )

        cy.wait('@postRestaurantCart')

        cy.addProduct('Cheeseburger', '#CHEESEBURGER-options', 2, [
          'HAMBURGER_ACCOMPANIMENT_FRENCH_FRIES',
          'HAMBURGER_DRINK_COLA' ])

        cy.wait('@postProduct', { timeout: 5000 })

        cy.get('.cart__items').invoke('text').should('match', /Cheeseburger/)

        cy.searchAddress(
          '.ReactModal__Content--enter-address',
          '91 rue de rivoli paris',
          /^91,? Rue de Rivoli,? 75001,? Paris,? France/i,
        )

        cy.wait('@postRestaurantCart')

        cy.get(
          '#restaurant__fulfilment-details__container [data-testid="cart.shippingAddress"]')
          .invoke('text')
          .should('match', /^91,? Rue de Rivoli,? 75001,? Paris,? France/i)

        cy.get('.order-button:visible').click()

        cy.location('pathname').should('eq', '/order/')

        cy.get('input[name="checkout_address[customer][fullName]"]')
          .type('John Doe')

        cy.closeRestaurant('resto_1', 'resto_1')

        cy.contains('Commander').click()

        cy.get('form[name="checkout_address"]')
          .contains('Il n\'est plus possible de commander pour aujourd\'hui')
      })
    })

  context('restaurant is closed while the customer is on the payment page',
    () => {
      it('proceed with payment (FIXME)', () => {

        cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart')
        cy.intercept('POST', '/fr/restaurant/*/cart/product/*')
          .as('postProduct')

        cy.visit('/fr/')

        cy.clickRestaurant(
          'Crazy Hamburger',
          /\/fr\/restaurant\/[0-9]+-crazy-hamburger/,
        )

        cy.wait('@postRestaurantCart')

        cy.addProduct('Cheeseburger', '#CHEESEBURGER-options', 2, [
          'HAMBURGER_ACCOMPANIMENT_FRENCH_FRIES',
          'HAMBURGER_DRINK_COLA' ])

        cy.wait('@postProduct', { timeout: 5000 })

        cy.get('.cart__items').invoke('text').should('match', /Cheeseburger/)

        cy.searchAddress(
          '.ReactModal__Content--enter-address',
          '91 rue de rivoli paris',
          /^91,? Rue de Rivoli,? 75001,? Paris,? France/i,
        )

        cy.wait('@postRestaurantCart')

        cy.get(
          '#restaurant__fulfilment-details__container [data-testid="cart.shippingAddress"]')
          .invoke('text')
          .should('match', /^91,? Rue de Rivoli,? 75001,? Paris,? France/i)

        cy.get('.order-button:visible').click()

        cy.location('pathname').should('eq', '/order/')

        cy.get('input[name="checkout_address[customer][fullName]"]')
          .type('John Doe')

        cy.contains('Commander').click()

        cy.location('pathname').should('eq', '/order/payment')

        cy.get('form[name="checkout_payment"] input[type="text"]')
          .type('John Doe')
        cy.enterCreditCard()

        cy.closeRestaurant('resto_1', 'resto_1')

        cy.get('form[name="checkout_payment"]').submit()

        //FIXME: this behaviour is broken since https://github.com/coopcycle/coopcycle-web/pull/3971
        // and it will be re-introduced in https://github.com/coopcycle/coopcycle-web/issues/4167
        cy.get('#order-timeline').contains('Commande en attente de validation')

        // cy.get('form[name="checkout_address"]')
        //   .contains('Il n\'est plus possible de commander pour aujourd\'hui')
      })
    })
})
