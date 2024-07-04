context('Checkout', () => {
    beforeEach(() => {

      cy.symfonyConsole('coopcycle:fixtures:load -f cypress/fixtures/checkout.yml')

      cy.window().then((win) => {
        win.sessionStorage.clear()
      })
    })

    // fails on GitHub CI
    it.skip('start ordering in one restaurant, then navigate to another restaurant', () => {

        cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart')
        cy.intercept('POST', '/fr/restaurant/*/cart/product/*').as('postProduct')

        cy.visit('/fr/')

        cy.clickRestaurant(
          'Crazy Hamburger',
          /\/fr\/restaurant\/[0-9]+-crazy-hamburger/
        )

        cy.wait('@postRestaurantCart')

        cy.contains('Cheese Cake').click()

        cy.get('.product-modal-container button[type="submit"]').click()

        cy.wait('@postProduct', {timeout: 5000})

        cy.get('.ReactModal__Content--enter-address').should('be.visible')
        cy.get('.cart__items').invoke('text').should('match', /Cheese Cake/)

        cy.searchAddress(
          '.ReactModal__Content--enter-address',
          '91 rue de rivoli paris',
          '91 Rue De Rivoli, 75001 Paris, France'
        )

        cy.wait('@postRestaurantCart')

        cy.visit('/fr/')

        cy.clickRestaurant(
          'Pizza Express',
          /\/fr\/restaurant\/[0-9]+-pizza-express/
        )

        cy.get('#cart .panel-body .cart .alert-warning').should('have.text', 'Votre panier est vide')

        cy.contains('Pizza Margherita').click()

        cy.wait('@postProduct', {timeout: 5000})

        cy.get('.ReactModal__Content--restaurant').should('be.visible')
      })
})