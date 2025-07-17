context('Delivery (role: store)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup(['ORM/store_basic.yml'])
  })

  it('create delivery', () => {
    cy.intercept('/api/routing/route/*').as('apiRoutingRoute')

    cy.login('store_1', 'store_1')

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

    cy.get('[data-testid="tax-included"]').contains('4,99 €')

    cy.wait('@apiRoutingRoute')

    cy.get('[data-testid="delivery-distance"]')
      .invoke('text')
      .should('contains', 'Distance : 1.50 kms')

    cy.get('button[type="submit"]').click()

    // Delivery page (view mode)
    cy.urlmatch(/\/dashboard\/deliveries\/[0-9]+$/)
    cy.get('[data-testid=delivery-itinerary]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery-itinerary]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=tax-included-previous]').contains(/4.99/)
  })
})
