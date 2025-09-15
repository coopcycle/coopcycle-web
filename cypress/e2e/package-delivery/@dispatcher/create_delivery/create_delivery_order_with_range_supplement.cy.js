context('Delivery (role: dispatcher)', () => {
  beforeEach(() => {
    cy.loadFixtures([
      'setup_default.yml',
      'user_dispatcher.yml',
      'tags.yml',
      'store_with_range_supplements.yml',
    ]);
    cy.setEnvVar('PACKAGE_DELIVERY_UI_PRICE_BREAKDOWN_ENABLED', '1');

    cy.setMockDateTime('2025-04-23 8:30:00');

    cy.login('dispatcher', 'dispatcher');
  });

  afterEach(() => {
    cy.resetMockDateTime();
    cy.removeEnvVar('PACKAGE_DELIVERY_UI_PRICE_BREAKDOWN_ENABLED');
  });

  it('create delivery order with range-based supplement', function () {
    cy.visit('/admin/stores');

    cy.get('[data-testid=store_Store_with_Range_Supplements__list_item]')
      .find('.dropdown-toggle')
      .click();

    cy.get('[data-testid=store_Store_with_Range_Supplements__list_item]')
      .contains('Créer une nouvelle commande')
      .click();

    // Create delivery page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries\/new$/);

    // Pickup
    cy.betaChooseSavedAddressAtPosition(0, 1);

    // Dropoff
    cy.betaChooseSavedAddressAtPosition(1, 2);

    cy.betaEnterWeightAtPosition(1, 2.5);

    // Initial state - no supplements
    cy.get('[data-testid="tax-included"]').contains('4,99 €');

    // Add range-based supplement
    cy.get(
      '[data-testid="manual-supplement-range-Waiting time supplement"]',
    ).should('be.visible');

    // Initial state - no supplements
    cy.get('[data-testid="tax-included"]').contains('4,99 €');

    // Update waiting time supplement
    cy.get(
      '[data-testid="manual-supplement-range-Waiting time supplement"]',
    ).within(() => {
      cy.get('[data-testid="range-input-field"]').clear();
      cy.get('[data-testid="range-input-field"]').type('5');
    });

    // Verify price
    cy.get('[data-testid="tax-included"]').contains('9,99 €');

    // Change value and verify price updates again
    cy.get(
      '[data-testid="manual-supplement-range-Waiting time supplement"]',
    ).within(() => {
      cy.get('[data-testid="range-input-field"]').clear();
      cy.get('[data-testid="range-input-field"]').type('15');
    });

    // Verify total price includes supplement
    cy.get('[data-testid="tax-included"]').contains('19,99 €');

    cy.get('button[type="submit"]').click();

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/);

    cy.get('[data-testid="order-total-including-tax"]')
      .find('[data-testid="value"]')
      .contains('€19.99');
  });
});
