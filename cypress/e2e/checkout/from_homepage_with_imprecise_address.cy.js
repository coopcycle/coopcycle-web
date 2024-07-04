context('Checkout', () => {
    beforeEach(() => {

      cy.symfonyConsole('coopcycle:fixtures:load -f cypress/fixtures/checkout.yml')

      cy.window().then((win) => {
        win.sessionStorage.clear()
      })
    })

    it('homepage search with imprecise address', () => {

        cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart')
        cy.intercept('GET', '/search/geocode?address=**').as('geocodeAddress')

        cy.visit('/fr/')

        cy.get('[data-search="address"] input[type="search"]')
          .type('rue de rivoli paris', { timeout: 5000, delay: 30 })

        cy.get('[data-search="address"]')
          .find('ul[role="listbox"] li', { timeout: 5000 })
          .contains('Rue de Rivoli, Paris, France')
          .click()

        cy.location('pathname').should('match', /\/fr\/restaurants/)

        cy.contains('Crazy Hamburger').click()

        cy.location('pathname').should('match', /\/fr\/restaurant\/[0-9]+-crazy-hamburger/)

        cy.get('#restaurant__fulfilment-details__container [data-testid="cart.shippingAddress"]')
          .should('have.text', 'Rue de Rivoli, Paris, France')

        cy.wait('@geocodeAddress')

        cy.get('.ReactModal__Content--enter-address')
          .should('be.visible')

        cy.get('.ReactModal__Content--enter-address')
          .invoke('text')
          .should('match', /Cette adresse n'est pas assez précise/)

        cy.searchAddress(
            '.ReactModal__Content--enter-address',
            '91 rue de rivoli paris',
            '91 Rue De Rivoli, 75001 Paris, France'
          )

        cy.wait('@postRestaurantCart')

        cy.get('#restaurant__fulfilment-details__container [data-testid="cart.shippingAddress"]')
          .should('have.text', '91 Rue De Rivoli, 75001 Paris, France')
      })
})