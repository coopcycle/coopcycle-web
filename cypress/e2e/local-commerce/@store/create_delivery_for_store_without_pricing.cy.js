context('store without pricing (role: store)', () => {
  beforeEach(() => {
    cy.loadFixtures('stores.yml')
  })

  it('create delivery for store without pricing', () => {
    cy.intercept('/api/routing/route/*').as('apiRoutingRoute')

    cy.login('store_no_pricing', 'password')

    cy.urlmatch(/\/dashboard$/)

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

    cy.urlmatch(/\/dashboard\/stores\/[0-9]+\/deliveries$/)
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
