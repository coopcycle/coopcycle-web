context(
  'start ordering in one restaurant, then navigate to another restaurant',
  () => {
    beforeEach(() => {
      cy.symfonyConsole(
        'coopcycle:fixtures:load -f cypress/fixtures/checkout.yml',
      )
    })

    it('should show a prompt to either start a new order or return to a previous order', () => {
      cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart')
      cy.intercept('POST', '/fr/restaurant/*/cart/product/*').as('postProduct1')

      cy.visit('/fr/')

      cy.clickRestaurant(
        'Crazy Hamburger',
        /\/fr\/restaurant\/[0-9]+-crazy-hamburger/,
      )

      cy.wait('@postRestaurantCart')

      cy.addProduct('Cheese Cake', '#CHEESECAKE_crazy_hamburger-options')

      cy.wait('@postProduct1', { timeout: 5000 })

      cy.get('.ReactModal__Content--enter-address').should('be.visible')
      cy.get('.cart__items')
        .invoke('text')
        .should('match', /Cheese Cake/)

      cy.searchAddressUsingAddressModal(
        '.ReactModal__Content--enter-address',
        '91 rue de rivoli paris',
        /^91,? Rue de Rivoli,? 75001,? Paris,? France/i,
      )

      cy.wait('@postRestaurantCart')

      cy.visit('/fr/')

      cy.clickRestaurant(
        'Pizza Express',
        /\/fr\/restaurant\/[0-9]+-pizza-express/,
      )

      cy.intercept('POST', '/fr/restaurant/*/cart/product/*').as('postProduct2')

      cy.get('[data-testid="cart.empty"]').should('be.visible')

      cy.addProduct('Pizza Margherita', '#PIZZA_MARGHERITA-options')

      cy.wait('@postProduct2', { timeout: 5000 })

      cy.get('.ReactModal__Content--restaurant').should('be.visible')
    })
  },
)
