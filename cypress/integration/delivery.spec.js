context('Delivery', () => {
  beforeEach(() => {

    const prefix = Cypress.env('COMMAND_PREFIX')

    let cmd = 'bin/console coopcycle:fixtures:load -f cypress/fixtures/stores.yml --env test'
    if (prefix) {
      cmd = `${prefix} ${cmd}`
    }

    cy.exec(cmd)
  })

  it('create delivery', () => {

    cy.intercept('/api/routing/route/*').as('apiRoutingRoute')

    cy.visit('/login')

    cy.get('[name="_username"]').type('store_1')
    cy.get('[name="_password"]').type('store_1')
    cy.get('[name="_submit"]').click()

    cy.location('pathname').should('eq', '/dashboard')

    cy.get('a').contains('Créer une livraison').click()

    cy.get('[data-form="task"]').eq(0).find('input[type="search"]')
      .type('23 av claude vellefaux', { timeout: 15000 })
    cy.contains('23 Avenue Claude Vellefaux, 75010 Paris, France').click()

    cy.get('[data-form="task"]').eq(1).find('input[type="search"]')
      .type('72 rue st maur', { timeout: 15000 })
    cy.contains('72 Rue Saint-Maur, 75011 Paris, France').click()

    cy.wait('@apiRoutingRoute')

    cy.get('#delivery_distance').invoke('text').should('match', /[0-9\.]+ Km/)

  })

  it('create delivery via form', () => {

    cy.visit('/fr/embed/delivery/start')

    // Pickup

    cy.get('[data-form="task"]').eq(0).find('input[type="search"]')
      .type('91 rue de rivoli paris', { timeout: 5000, delay: 30 })

    cy.get('[data-form="task"]').eq(0)
      .find('.react-autosuggest__suggestions-container')
      .find('ul[role="listbox"] li', { timeout: 5000 })
      .contains('91 Rue de Rivoli, 75004 Paris, France')
      .click()

    // Dropoff

    cy.get('[data-form="task"]').eq(1).find('input[type="search"]')
      .type('120 rue st maur paris', { timeout: 5000, delay: 30 })

    // Click on the first suggestion
    cy.get('[data-form="task"]').eq(1)
      .find('.react-autosuggest__suggestions-container')
      .find('ul[role="listbox"] li', { timeout: 5000 })
      .contains('120 Rue Saint-Maur')
      .click()

    cy.get('[data-form="task"]')
      .each(($el) => {
        cy.wrap($el).find('[id$="address_newAddress_latitude"]')
          .invoke('val')
          .should('match', /[0-9\.]+/)
        cy.wrap($el).find('[id$="address_newAddress_longitude"]')
          .invoke('val')
          .should('match', /[0-9\.]+/)
      })

    cy.get('#delivery_name').type('John Doe')
    cy.get('#delivery_email').type('dev@coopcycle.org')
    cy.get('#delivery_telephone').type('0612345678')

    cy.get('form[name="delivery"] button[type="submit"]').click()

    cy.location('pathname').should('match', /\/fr\/forms\/[a-zA-Z0-9]+\/summary/)

    cy.get('.alert-info')
      .invoke('text')
      .should('match', /Vous avez demandé une course qui vous sera déposée le/)

    cy.get('form[name="checkout_payment"] input[type="text"]').type('John Doe')
    cy.enterCreditCard()

    cy.get('form[name="checkout_payment"] button[type="submit"]').click()

    cy.location('pathname', { timeout: 30000 }).should('match', /\/fr\/pub\/o\/[a-zA-Z0-9]+/)
  })
})
