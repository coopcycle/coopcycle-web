context('Delivery (role: dispatcher)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup([
      'user_dispatcher.yml',
      'store_with_task_pricing.yml',
    ]);
    cy.setEnvVar('PACKAGE_DELIVERY_UI_PRICE_BREAKDOWN_ENABLED', '1');
    cy.setMockDateTime('2025-04-23 8:30:00');
    cy.login('dispatcher', 'dispatcher');
  });

  afterEach(() => {
    cy.resetMockDateTime();
    cy.removeEnvVar('PACKAGE_DELIVERY_UI_PRICE_BREAKDOWN_ENABLED');
  });

  it('modify delivery with recalculatePrice should recalculate price', function () {
    // This test verifies that when modifying a delivery and selecting "Apply new price",
    // triggers price recalculation
    // Initial: 1 PICKUP + 2 DROPOFF = €4.99 + €2.00 + €2.00 = €8.99
    // After removing 1 DROPOFF with recalculation: should become €6.99
    cy.visit('/admin/stores/1/deliveries/new');

    // Create delivery with 1 pickup + 2 dropoffs to get €8.99 total
    cy.betaChooseSavedAddressAtPosition(0, 1);

    cy.betaChooseSavedAddressAtPosition(1, 2);

    cy.get('[data-testid="add-dropoff-button"]').click();
    cy.betaEnterAddressAtPosition(
      2,
      '72 Rue Saint-Maur, 75011 Paris, France',
      /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
      'Office 2',
      '+33112121415',
      'Jane Doe',
    );

    cy.get('[data-testid="tax-included"]').contains('8,99 €');

    cy.get('button[type="submit"]').click();

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/);

    cy.get('[data-testid="order-total-including-tax"]')
      .find('[data-testid="value"]')
      .contains('€8.99');

    cy.get('[data-testid="order-edit"]').click();

    // Edit Delivery page
    cy.urlmatch(/\/admin\/deliveries\/[0-9]+$/);

    cy.get('[data-testid="tax-included"]').contains('8,99 €');

    // Remove one dropoff task to trigger price change detection
    // This should change the price from €8.99 to €6.99 when recalculated
    cy.get('[data-testid="form-task-2"]').within(() => {
      cy.get('[data-testid="task-remove"]').click();
    });

    cy.get('[data-testid="keep-original-price"]', { timeout: 10000 }).should(
      'be.checked',
    );

    cy.get('[data-testid="apply-new-price"]').check();

    cy.get('[data-testid="apply-new-price"]').should('be.checked');
    cy.get('[data-testid="keep-original-price"]').should('not.be.checked');

    // The price should be recalculated to €6.99 (1 PICKUP + 1 DROPOFF)
    cy.get('[data-testid="tax-included"]').contains('6,99 €');

    cy.get('button[type="submit"]').click();

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/);

    cy.get('[data-testid="order-total-including-tax"]')
      .find('[data-testid="value"]')
      .contains('€6.99');
  });
});
