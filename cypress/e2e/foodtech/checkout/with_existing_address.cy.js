context('Checkout', () => {
  beforeEach(() => {
    cy.loadFixtures('checkout.yml')
  })

  it('order something at restaurant with existing address', () => {
    cy.login('jane', '12345678')

    cy.urlmatch(/\/fr\/$/)

    cy.get('[data-search="address"] input[type="search"]')
      .type('1 rue de', { timeout: 5000, delay: 300 })

    cy.get('[data-search="address"]')
      .find('div[role="listbox"]', { timeout: 5000 })
      .find('.react-autosuggest__section-container', { timeout: 5000 })
      // There should be 2 sections
      .then(($sections) => {
        cy.wrap($sections).should('have.length', 2)
      })
      // The first section should contain saved addresses
      .then(($sections) => {
        cy.wrap($sections)
          .eq(0)
          .find('h5')
          .invoke('text')
          .should('eq', 'Adresses sauvegardées')
      })

    // Click on the first suggestion
    cy.get('[data-search="address"]')
      .find('div[role="listbox"]')
      .find('.react-autosuggest__section-container')
      .eq(0)
      .contains('1, Rue de Rivoli, Paris, France')
      .click()

    cy.urlmatch(/\/fr\/shops$/)
    cy.urlmatch(/\?geohash=[a-z0-9]+&address=[A-Za-z0-9%=]+/, 'match', 'search')

    cy.contains('Crazy Hamburger').click()

    cy.get('#restaurant__fulfilment-details__container [data-testid="cart.shippingAddress"]')
      .should('have.text', '1, Rue de Rivoli, Paris, France')
  })
})
