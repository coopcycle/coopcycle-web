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
})