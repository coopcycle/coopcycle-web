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

    cy.server()
    cy.route('/api/routing/route/*').as('apiRoutingRoute')

    cy.visit('/login')

    cy.get('[name="_username"]').type('store_1')
    cy.get('[name="_password"]').type('store_1')
    cy.get('[name="_submit"]').click()

    cy.location('pathname').should('eq', '/profile/')

    cy.get('a').contains('Créer une livraison').click()

    // TODO Use data attributes instead of CSS selectors
    // https://docs.cypress.io/guides/references/best-practices.html#Selecting-Elements
    cy.get('#delivery_pickup_address input[type="search"]')
        .type('23 av claude vellefaux', { timeout: 15000 })
    cy.contains('23 Avenue Claude Vellefaux, Paris, France').click()

    cy.get('#delivery_dropoff_address input[type="search"]')
        .type('72 rue st maur', { timeout: 15000 })
    cy.contains('72 Rue St Maur, Paris, France').click()

    cy.wait('@apiRoutingRoute')

    cy.get('#delivery_distance').invoke('text').should('match', /[0-9\.]+ Km/)

  })

  it('create delivery via form', () => {

    cy.visit('/fr/embed/delivery/start')

    // Pickup

    cy.get('#delivery_pickup_address input[type="search"]')
      .type('91 rue de rivoli paris', { timeout: 5000, delay: 30 })

    cy.get('#delivery_pickup_address')
      .find('.react-autosuggest__suggestions-container')
      .find('ul[role="listbox"] li', { timeout: 5000 })
      .contains('91 Rue de Rivoli, Paris, France')
      .click()

    cy.get('#delivery_pickup_address_newAddress_latitude')
      .invoke('val')
      .should('match', /[0-9\.]+/)

    // Dropoff

    cy.get('#delivery_dropoff_address input[type="search"]')
      .type('120 rue st maur paris', { timeout: 5000, delay: 30 })

    // Click on the first suggestion
    cy.get('#delivery_dropoff_address')
      .find('.react-autosuggest__suggestions-container')
      .find('ul[role="listbox"] li', { timeout: 5000 })
      .contains('120 Rue St Maur')
      .click()

    cy.get('#delivery_dropoff_address_newAddress_latitude')
      .invoke('val')
      .should('match', /[0-9\.]+/)

    cy.get('#delivery_name').type('John Doe')
    cy.get('#delivery_email').type('dev@coopcycle.org')
    cy.get('#delivery_telephone').type('0612345678')

    cy.get('form[name="delivery"] button[type="submit"]').click()

    cy.location('pathname').should('match', /\/fr\/forms\/[a-zA-Z0-9]+\/summary/)

    cy.get('form[name="delivery"] .alert-info')
      .invoke('text')
      .should('match', /Vous avez demandé une course qui vous sera déposée le/)
  })
})
