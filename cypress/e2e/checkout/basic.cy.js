context('Checkout', () => {
    beforeEach(() => {

      cy.symfonyConsole('coopcycle:fixtures:load -f cypress/fixtures/checkout.yml')

      cy.window().then((win) => {
        win.sessionStorage.clear()
      })
    })

    it('order something at restaurant', () => {

        cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart')
        cy.intercept('POST', '/fr/restaurant/*/cart/product/*').as('postProduct')

        cy.visit('/fr/')

        cy.clickRestaurant(
          'Crazy Hamburger',
          /\/fr\/restaurant\/[0-9]+-crazy-hamburger/
        )

        cy.wait('@postRestaurantCart')

        cy.contains('Cheeseburger').click()

        cy.get('#CHEESEBURGER-options')
          .should('be.visible')

        // Make sure to use a precise selector, because 2 products have same options
        cy.get('#CHEESEBURGER-options input[value="HAMBURGER_ACCOMPANIMENT_FRENCH_FRIES"]')
          .check()
        cy.get('#CHEESEBURGER-options input[value="HAMBURGER_DRINK_COLA"]')
          .check()

        cy.get('#CHEESEBURGER-options input[value="HAMBURGER_ACCOMPANIMENT_FRENCH_FRIES"]').should('be.checked')
        cy.get('#CHEESEBURGER-options input[value="HAMBURGER_DRINK_COLA"]').should('be.checked')

        cy.get('#CHEESEBURGER-options button[type="submit"]')
          .should('not.be.disabled')
          .click()

        cy.wait('@postProduct', {timeout: 5000})

        cy.get('.cart__items').invoke('text').should('match', /Cheeseburger/)

        cy.searchAddress(
          '.ReactModal__Content--enter-address',
          '91 rue de rivoli paris',
          '91 Rue De Rivoli, 75001 Paris, France'
        )

        cy.wait('@postRestaurantCart')

        cy.get('#restaurant__fulfilment-details__container [data-testid="cart.shippingAddress"]')
          .should('have.text', '91 Rue De Rivoli, 75001 Paris, France')

        cy.contains('Cheese Cake').click()

        cy.get('.product-modal-container button[type="submit"]').click()

        cy.wait('@postProduct', {timeout: 5000})

        cy.get('.cart__items', {timeout: 10000}).invoke('text').should('match', /Cheese Cake/)

        cy.get('form[name="cart"]').submit()

        cy.location('pathname').should('eq', '/login')

        cy.login('bob', '12345678')

        cy.location('pathname').should('eq', '/order/')

        cy.get('input[name="checkout_address[customer][fullName]"]').type('John Doe')

        cy.contains('Commander').click()

        cy.location('pathname').should('eq', '/order/payment')

        cy.get('form[name="checkout_payment"] input[type="text"]').type('John Doe')
        cy.enterCreditCard()

        cy.get('form[name="checkout_payment"]').submit()

        cy.location('pathname', { timeout: 30000 }).should('match', /\/order\/confirm\/[a-zA-Z0-9]+/)

        cy.get('#order-timeline').contains('Commande en attente de validation')
      })
})