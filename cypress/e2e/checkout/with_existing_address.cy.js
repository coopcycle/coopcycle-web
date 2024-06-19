context('Checkout', () => {
    beforeEach(() => {

      cy.symfonyConsole('coopcycle:fixtures:load -f cypress/fixtures/checkout.yml')

      cy.window().then((win) => {
        win.sessionStorage.clear()
      })
    })

    it('order something at restaurant with existing address', () => {

        cy.visit('/login')

        cy.login('jane', '12345678')

        cy.location('pathname').should('eq', '/fr/')

        cy.get('[data-search="address"] input[type="search"]')
          .type('1 rue de', { timeout: 5000, delay: 300 })

        cy.get('[data-search="address"]')
          .find('.react-autosuggest__suggestions-container', { timeout: 5000 })
          .find('.react-autosuggest__section-container', { timeout: 5000 })
          // There should be 2 sections
          .then(($sections) => {
            cy.wrap($sections).should('have.length', 2)
          })
          // The first section should contain saved addresses
          .then(($sections) => {
            cy.wrap($sections)
              .eq(0)
              .find('.react-autosuggest__section-title')
              .invoke('text')
              .should('eq', 'Adresses sauvegardées')
          })

        // Click on the first suggestion
        cy.get('[data-search="address"]')
          .find('.react-autosuggest__suggestions-container')
          .find('.react-autosuggest__section-container')
          .eq(0)
          .contains('1, Rue de Rivoli, Paris, France')
          .click()

        cy.location().then((loc) => {
          expect(loc.pathname).to.eq('/fr/restaurants')
          expect(loc.search).to.match(/\?geohash=[a-z0-9]+&address=[A-Za-z0-9%=]+/)
        })

        cy.contains('Crazy Hamburger').click()

        cy.get('#restaurant__fulfilment-details__container [data-testid="cart.shippingAddress"]')
          .should('have.text', '1, Rue de Rivoli, Paris, France')
    })
})
