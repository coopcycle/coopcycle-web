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

    cy.location('pathname').should('eq', '/profile/stores')

    cy.get('.content .table tbody tr:first-child td:first-child a').click()

    cy.get('a').contains('Créer une livraison').click()

    // TODO Use data attributes instead of CSS selectors
    // https://docs.cypress.io/guides/references/best-practices.html#Selecting-Elements
    cy.get('#delivery_pickup_address_streetAddress_widget input[type="search"]')
        .type('23 av claude vellefaux', { timeout: 15000 })
    cy.contains('23 Avenue Claude Vellefaux, Paris, France').click()

    cy.get('#delivery_dropoff_address_streetAddress_widget input[type="search"]')
        .type('72 rue st maur', { timeout: 15000 })
    cy.contains('72 Rue Saint-Maur, Paris, France').click()

    cy.wait('@apiRoutingRoute')

    cy.get('#delivery_distance').invoke('text').should('match', /[0-9\.]+ Km/)
    cy.get('#delivery_duration').invoke('text').should('match', /[0-9]+ min/)

  })
})
