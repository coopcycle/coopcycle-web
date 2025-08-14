describe(
  'guest checkout: Failed; restaurant is closed while the customer is on the payment page',
  () => {
    [ 'guest checkout' ].forEach((customerType) => {

      describe(` (${ customerType })`, () => {

        beforeEach(() => {
          cy.loadFixtures('ORM/checkout.yml')

          cy.setMockDateTime('2025-01-10 21:30:00')

          cy.symfonyConsole('craue:setting:create --section="general" --name="guest_checkout_enabled" --value="1" --force')
        })

        afterEach(() => {
          cy.resetMockDateTime()
        })

        context(
          'restaurant is closed while the customer is on the payment page' +
          ` (${ customerType })`,
          () => {
            it('suggest to choose a new time range (Timing modal)' +
              ` (${ customerType })`, () => {

              cy.visit('/fr/')

              cy.intercept('POST', '/fr/restaurant/*/cart')
                .as('postRestaurantCart1')

              cy.clickRestaurant(
                'Crazy Hamburger',
                /\/fr\/restaurant\/[0-9]+-crazy-hamburger/,
              )

              //FIXME: why do we send two requests?
              cy.wait([ '@postRestaurantCart1', '@postRestaurantCart1' ])

              cy.intercept('POST', '/fr/restaurant/*/cart/product/*')
                .as('postProduct1')

              cy.addProduct('Cheeseburger',
                '#CHEESEBURGER_crazy_hamburger-options', 2, [
                  'HAMBURGER_ACCOMPANIMENT_FRENCH_FRIES_crazy_hamburger',
                  'HAMBURGER_DRINK_COLA_crazy_hamburger' ])

              cy.wait('@postProduct1', { timeout: 5000 })

              cy.get('.cart__items')
                .invoke('text')
                .should('match', /Cheeseburger/)

              cy.intercept('POST', '/fr/restaurant/*/cart')
                .as('postRestaurantCart2')

              cy.searchAddressUsingAddressModal(
                '.ReactModal__Content--enter-address',
                '91 rue de rivoli paris',
                /^91,? Rue de Rivoli,? 75001,? Paris,? France/i,
              )

              cy.wait('@postRestaurantCart2')

              cy.get(
                '#restaurant__fulfilment-details__container [data-testid="cart.shippingAddress"]')
                .invoke('text')
                .should('match',
                  /^91,? Rue de Rivoli,? 75001,? Paris,? France/i)

              cy.get('.order-button:visible').click()

              cy.urlmatch(/\/order\/$/)

              cy.get('input[name="checkout_address[customer][fullName]"]')
                .type('John Doe')

              if (customerType === 'guest checkout') {
                cy.get('input[name="checkout_address[customer][email]"]')
                  .type('test@gmail.com')

                cy.get('input[name="checkout_address[customer][phoneNumber]"]')
                  .type('+33612345678')

                cy.get('input[name="checkout_address[customer][legal]"]')
                  .check()
              }

              cy.contains('Commander').click()

              cy.urlmatch(/\/order\/payment$/)

              cy.get('form[name="checkout_payment"] input[type="text"]')
                .type('John Doe')
              cy.enterCreditCard()

              cy.closeRestaurantForToday('resto_1', 'resto_1')

              cy.get('form[name="checkout_payment"]').submit()

              cy.get('[data-testid="order.timeRangeChangedModal"]')
                .should('be.visible')

              cy.intercept('PUT', '/api/orders/*')
                .as('putOrder1')
              cy.get(
                '[data-testid="order.timeRangeChangedModal.setTimeRange"]:visible button')
                .click()
              cy.wait('@putOrder1')

              cy.get(
                '[data-testid="order.time"]:visible')
                .invoke('text')
                .should('match', /^Demain entre 09:50 et 10:00/i)

              cy.intercept('POST', '/stripe/payment/*/create-intent')
                .as('createStripePaymentIntent')

              cy.get('form[name="checkout_payment"]').submit()

              cy.wait('@createStripePaymentIntent', { timeout: 5000 })

              cy.get('#order-timeline')
                .contains('Commande en attente de validation')
            })
          })
      })
    })
  })
