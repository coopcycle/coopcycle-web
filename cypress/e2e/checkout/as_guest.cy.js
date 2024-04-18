context('Checkout', () => {
    beforeEach(() => {

      cy.symfonyConsole('coopcycle:fixtures:load -f cypress/fixtures/checkout.yml')

      cy.window().then((win) => {
        win.sessionStorage.clear()
      })
    })

    it('order something at restaurant with a tip (as guest)', () => {

        cy.symfonyConsole('craue:setting:create --section="general" --name="guest_checkout_enabled" --value="1" --force')

        cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart')
        cy.intercept('POST', '/fr/restaurant/*/cart/product/*').as('postProduct')
        cy.intercept('POST', '/order/').as('postOrder')
        cy.intercept('GET', '/search/geocode?address=**').as('geocodeAddress')

        cy.visit('/fr/')

        cy.contains('Crazy Hamburger').click()

        cy.location('pathname').should('match', /\/fr\/restaurant\/[0-9]+-crazy-hamburger/)

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

        cy.get('.cart__items').invoke('text').should('match', /Cheese Cake/)

        // FIXME Use click instead of submit
        cy.get('form[name="cart"]').submit()

        cy.location('pathname').should('eq', '/order/')

        cy.get('.table-order-items tfoot tr:last-child td')
          .invoke('text')
          .invoke('trim')
          .should('equal', "20, 00 €")

        cy.get('#tip-incr').click()
        cy.wait('@postOrder')

        cy.get('.loadingoverlay', { timeout: 15000 }).should('not.exist')

        cy.get('.table-order-items tfoot tr:last-child td')
          .invoke('text')
          .invoke('trim')
          .should('equal', "21, 00 €")
      })
})