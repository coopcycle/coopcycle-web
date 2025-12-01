context('Delivery (role: dispatcher)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup([
      'user_dispatcher.yml',
      'store_default.yml',
    ])
    cy.setMockDateTime('2025-04-23 8:30:00')
    cy.login('dispatcher', 'dispatcher')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  it('update price calculated by pricing rules', function () {
    // Create a delivery order with a price calculated by pricing rules

    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une nouvelle commande')
      .click()

    // Create delivery page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries\/new$/)

    // Pickup
    cy.betaChooseSavedAddressAtPosition(0, 1)

    // Dropoff
    cy.betaChooseSavedAddressAtPosition(1, 2)

    cy.get('[data-testid="tax-included"]').contains('4,99 €')

    cy.get('button[type="submit"]').click()

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    cy.get('[data-testid="order-total-including-tax"]')
      .find('[data-testid="value"]')
      .contains('€4.99')

    cy.get('[data-testid="order-edit"]').click()

    // Delivery page
    cy.urlmatch(/\/admin\/deliveries\/[0-9]+$/)

    cy.get('[data-testid="tax-included"]').contains('4,99 €')

    cy.get('[name="delivery.override_price"]').check()

    cy.get('[name="variantName"]').type('Test product')
    cy.get('#variantPriceVAT').type('72')

    cy.get('[data-testid="tax-included-previous"]').contains('4,99 €')

    cy.get('button[type="submit"]').click()

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    cy.get('[data-testid="order-total-including-tax"]')
      .find('[data-testid="value"]')
      .contains('€72.00')

    // Wait for React components to load
    cy.get('[data-testid="delivery-itinerary"]', {
      timeout: 10000,
    }).should('be.visible')
    cy.get('[data-testid=delivery-itinerary]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery-itinerary]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')

    // Open order history modal
    cy.contains('button', "Afficher l'historique").click();

    cy.get('.ant-modal').within(() => {
      cy.get('.ant-modal-title').should('contain', 'Historique de la commande');

      // Verify the price update event is displayed in the timeline
      cy.get('.ant-timeline-item')
        .contains('.ant-timeline-item-content', 'order:price_updated')
        .parent('.ant-timeline-item')
        .within(() => {
          cy.get('[data-testid="tax-included-previous"]').contains('4,99 €');
          cy.get('[data-testid="tax-included"]').contains('72,00 €');
        });
    });
  })
})
