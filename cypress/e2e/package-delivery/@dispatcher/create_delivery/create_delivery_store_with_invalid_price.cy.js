context('Delivery (role: admin)', () => {
  beforeEach(() => {
    cy.loadFixtures('../cypress/fixtures/stores.yml')
    cy.setMockDateTime('2025-04-23 8:30:00')
    cy.login('admin', '12345678')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  it('create delivery for store with invalid pricing', function () {
    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme_with_invalid_pricing__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme_with_invalid_pricing__list_item]')
      .contains('Créer une livraison')
      .click()

    cy.wait(500)

    // Create delivery page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries\/new$/)

    // Pickup

    cy.betaEnterAddressAtPosition(
      0,
      '23 Avenue Claude Vellefaux, 75010 Paris, France',
      /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
      'Office',
      '+33112121212',
      'John Doe',
      'Pickup comments',
    )

    // Dropoff

    cy.betaEnterAddressAtPosition(
      1,
      '72 Rue Saint-Maur, 75011 Paris, France',
      /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
      'Office',
      '+33112121212',
      'Jane smith',
      'Dropoff comments',
    )

    cy.get(`[name="tasks[1].weight"]`).clear()
    cy.get(`[name="tasks[1].weight"]`).type(2.5)

    cy.get('[data-testid="tax-included"]').should('not.exist')

    cy.get('.alert-danger', { timeout: 10000 }).should(
      'contain',
      "Le prix n'a pas pu être calculé. Vous pouvez créer la livraison, n'oubliez pas de corriger la règle de prix liée à ce magasin.",
    )

    cy.get('button[type="submit"]').click()

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    cy.get('[data-testid="order_item"]')
      .find('[data-testid="total"]')
      .contains('€0.00')

    cy.get('[data-testid=delivery-itinerary]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery-itinerary]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')
  })
})
