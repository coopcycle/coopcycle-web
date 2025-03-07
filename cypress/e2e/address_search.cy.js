context('Address search', () => {
  beforeEach(() => {

    cy.symfonyConsole('coopcycle:fixtures:load -f cypress/fixtures/checkout.yml')

  })

  it('search address with useless address details', () => {

    cy.visit('/fr/')

    // Start typing "4 av victoria paris 4"
    cy.get('[data-search="address"] input[type="search"]')
      .type('4 av victoria paris 4', { timeout: 5000, delay: 30 })

    cy.get('[data-search="address"]')
      .find('ul[role="listbox"] li', { timeout: 5000 })
      .invoke('text')
      .should('include', '4 Avenue Victoria, 75004 Paris, France')

    // Lose focus
    cy.get('.react-autosuggest__input.address-autosuggest__input').blur()

    // Set focus again
    cy.get('.react-autosuggest__input.address-autosuggest__input').focus()

    cy.get('[data-search="address"]')
      .find('ul[role="listbox"] li', { timeout: 5000 })
      .invoke('text')
      .should('include', '4 Avenue Victoria, 75004 Paris, France')

    // Delete 2 chars
    cy.get('[data-search="address"] input[type="search"]')
      .type('{backspace}{backspace}', { timeout: 5000, delay: 30 })

    cy.get('[data-search="address"]')
      .find('ul[role="listbox"] li', { timeout: 5000 })
      .invoke('text')
      .should('include', '4 Avenue Victoria, 75004 Paris, France')
  })
})
