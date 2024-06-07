describe('Failed checkout; restaurant is closed', () => {
  [ 'logged in customer', 'guest checkout' ].forEach((customerType) => {

    beforeEach(() => {

      cy.symfonyConsole(
        'coopcycle:fixtures:load -f cypress/fixtures/checkout.yml')

      cy.window().then((win) => {
        win.sessionStorage.clear()
      })

      if (customerType === 'logged in customer') {
        cy.visit('/login')
        cy.login('bob', '12345678')
      }

      if (customerType === 'guest checkout') {
        cy.symfonyConsole(
          'craue:setting:create --section="general" --name="guest_checkout_enabled" --value="1" --force')
      }
    })

    context('restaurant is closed while the customer is on the menu page' +
      ` (${ customerType })`, () => {
      it('suggest to choose a new time range' + ` (${ customerType })`, () => {

        cy.visit('/fr/')

        cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart1')

        cy.clickRestaurant(
          'Crazy Hamburger',
          /\/fr\/restaurant\/[0-9]+-crazy-hamburger/,
        )

        cy.wait('@postRestaurantCart1')

        cy.intercept('POST', '/fr/restaurant/*/cart/product/*')
          .as('postProduct1')

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

        cy.closeRestaurantForToday('resto_1', 'resto_1')

        cy.get('.order-button:visible').click()

        cy.get('[data-testid="cart.timeRangeChangedModal"]')
          .should('be.visible')

        cy.get('[data-testid="cart.timeRangeChangedModal"]:visible button')
          .click()

        cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart3')
        cy.get('[data-testid="cart.time.submit"]:visible').click()
        cy.wait('@postRestaurantCart3')

        cy.get(
          '[data-testid="cart.time"]:visible')
          .invoke('text')
          .should('match', /^Demain entre 10:00 et 10:10/i)

        cy.get('.order-button:visible').click()

        cy.location('pathname').should('eq', '/order/')
      })
    })

    context('restaurant is closed while the customer is on the address page' +
      ` (${ customerType })`,
      () => {
        it('show an error message (address page)' + ` (${ customerType })`,
          () => {

            cy.intercept('POST', '/fr/restaurant/*/cart')
              .as('postRestaurantCart')
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

            cy.get('.cart__items')
              .invoke('text')
              .should('match', /Cheeseburger/)

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

            cy.closeRestaurantForToday('resto_1', 'resto_1')

            cy.contains('Commander').click()

            cy.get('form[name="checkout_address"]')
              .contains(
                'Il n\'est plus possible de commander pour aujourd\'hui')
          })
      })

    context('restaurant is closed while the customer is on the payment page' +
      ` (${ customerType })`,
      () => {
        it('proceed with payment (FIXME)' + ` (${ customerType })`, () => {

          cy.visit('/fr/')

          cy.intercept('POST', '/fr/restaurant/*/cart')
            .as('postRestaurantCart1')

          cy.clickRestaurant(
            'Crazy Hamburger',
            /\/fr\/restaurant\/[0-9]+-crazy-hamburger/,
          )

          cy.wait('@postRestaurantCart1')

          cy.intercept('POST', '/fr/restaurant/*/cart/product/*')
            .as('postProduct1')

          cy.addProduct('Cheeseburger', '#CHEESEBURGER-options', 2, [
            'HAMBURGER_ACCOMPANIMENT_FRENCH_FRIES',
            'HAMBURGER_DRINK_COLA' ])

          cy.wait('@postProduct1', { timeout: 5000 })

          cy.get('.cart__items').invoke('text').should('match', /Cheeseburger/)

          cy.intercept('POST', '/fr/restaurant/*/cart')
            .as('postRestaurantCart2')

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

          cy.get('.order-button:visible').click()

          cy.location('pathname').should('eq', '/order/')

          cy.get('input[name="checkout_address[customer][fullName]"]')
            .type('John Doe')

          cy.contains('Commander').click()

          cy.location('pathname').should('eq', '/order/payment')

          cy.get('form[name="checkout_payment"] input[type="text"]')
            .type('John Doe')
          cy.enterCreditCard()

          cy.closeRestaurantForToday('resto_1', 'resto_1')

          cy.get('form[name="checkout_payment"]').submit()

          //FIXME: this behaviour is broken since https://github.com/coopcycle/coopcycle-web/pull/3971
          // and it will be re-introduced in https://github.com/coopcycle/coopcycle-web/issues/4167
          cy.get('#order-timeline')
            .contains('Commande en attente de validation')

          // cy.get('form[name="checkout_address"]')
          //   .contains('Il n\'est plus possible de commander pour aujourd\'hui')
        })
      })
  })
})
