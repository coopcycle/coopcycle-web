context('store with invalid pricing (role: store)', () => {
  beforeEach(() => {
    cy.loadFixtures('ORM/stores_legacy.yml')
  })

  it('create delivery for store with invalid pricing', () => {
    cy.intercept('/api/routing/route/*').as('apiRoutingRoute')

    cy.login('store_invalid_pricing', 'password')

    cy.urlmatch(/\/dashboard$/)

    cy.get('a').contains('Créer une nouvelle commande').click()

    // Pickup

    cy.betaEnterAddressAtPosition(
      0,
      '23 Avenue Claude Vellefaux, 75010 Paris, France',
      /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
      'Office',
      '+33112121212',
      'John Doe',
    )

    cy.betaEnterCommentAtPosition(0, 'Pickup comments')

    // Dropoff

    cy.betaEnterAddressAtPosition(
      1,
      '72 Rue Saint-Maur, 75011 Paris, France',
      /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
      'Office',
      '+33112121212',
      'Jane smith',
    )

    cy.betaEnterWeightAtPosition(1, 2.5)

    cy.betaEnterCommentAtPosition(1, 'Dropoff comments')

    cy.get('[data-testid="tax-included"]').should('not.exist')

    cy.get('.alert-danger', { timeout: 10000 }).should(
      'contain',
      "Le prix n'a pas pu être calculé. Vous pouvez créer la livraison, nous vous recontacterons avec le prix corrigé.",
    )

    cy.get('button[type="submit"]').click()

    // Delivery page (view mode)
    cy.urlmatch(/\/dashboard\/deliveries\/[0-9]+$/)
    cy.get('[data-testid=delivery-itinerary]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery-itinerary]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=tax-included-previous]')
      .contains(/0.00/)
  })
})
