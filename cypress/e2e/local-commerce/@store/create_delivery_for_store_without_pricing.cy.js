context('store without pricing (role: store)', () => {
  beforeEach(() => {
    const prefix = Cypress.env('COMMAND_PREFIX')

    let cmd =
      'bin/console coopcycle:fixtures:load -f cypress/fixtures/stores.yml --env test'
    if (prefix) {
      cmd = `${prefix} ${cmd}`
    }

    cy.exec(cmd)
  })

  it('create delivery for store without pricing', () => {
    cy.intercept('/api/routing/route/*').as('apiRoutingRoute')

    cy.visit('/login')

    cy.login('store_no_pricing', 'password')

    cy.location('pathname').should('eq', '/dashboard')

    cy.get('a').contains('Créer une livraison').click()

    // Pickup

    cy.betaEnterAddressAtPosition(
      0,
      '23 Avenue Claude Vellefaux, 75010 Paris, France',
      /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
      'Office',
      '+33112121212',
      'John Doe',
      'Pickup comments'
    )

    // Dropoff

    cy.betaEnterAddressAtPosition(
      1,
      '72 Rue Saint-Maur, 75011 Paris, France',
      /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
      'Office',
      '+33112121212',
      'Jane smith',
      'Dropoff comments'
    )

    cy.get(`[name="tasks[${1}].weight"]`).clear()
    cy.get(`[name="tasks[${1}].weight"]`).type(2.5)

    cy.get('button[type="submit"]').click()

    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/dashboard\/stores\/[0-9]+\/deliveries$/,
    )
    cy.get('[data-testid=delivery__list_item]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/€0.00/)
      .should('exist')
  })
})
