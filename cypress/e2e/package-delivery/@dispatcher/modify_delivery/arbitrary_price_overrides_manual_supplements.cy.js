describe('Edit Manual Supplements in Delivery', () => {
  beforeEach(() => {
    cy.loadFixtures([
      'setup_default.yml',
      'user_admin.yml',
      'user_dispatcher.yml',
      'tags.yml',
      'store_with_manual_supplements.yml',
    ]);
    cy.setEnvVar('PACKAGE_DELIVERY_UI_PRICE_BREAKDOWN_ENABLED', '1');

    cy.setMockDateTime('2025-04-23 8:30:00');

    cy.login('dispatcher', 'dispatcher');
  });

  afterEach(() => {
    cy.resetMockDateTime();
    cy.removeEnvVar('PACKAGE_DELIVERY_UI_PRICE_BREAKDOWN_ENABLED');
  });

  it('should override manual supplements with arbitrary price', function () {
    cy.visit('/admin/stores');

    cy.get('[data-testid=store_Store_with_Manual_Supplements__list_item]')
      .find('.dropdown-toggle')
      .click();

    cy.get('[data-testid=store_Store_with_Manual_Supplements__list_item]')
      .contains('Créer une nouvelle commande')
      .click();

    // Create delivery page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries\/new$/);

    // Pickup
    cy.betaChooseSavedAddressAtPosition(0, 1);

    // Dropoff
    cy.betaChooseSavedAddressAtPosition(1, 2);

    cy.get('[data-testid="tax-included"]').contains('4,99 €');

    // Add manual supplements (3,00 €)
    cy.get('[data-testid="manual-supplement-Fragile Handling"]').check();

    cy.get('[data-testid="tax-included"]').contains('7,99 €');

    cy.get('button[type="submit"]').click();

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/);

    // Verify initial supplements are displayed
    cy.get('[data-testid="order-total-including-tax"]')
      .find('[data-testid="value"]')
      .contains('€7.99');

    // Wait for React components to load
    cy.get('[data-testid="delivery-itinerary"]', {
      timeout: 10000,
    }).should('be.visible');

    // Edit the delivery
    cy.get('[data-testid="order-edit"]').click();

    // Edit Delivery page
    cy.urlmatch(/\/admin\/deliveries\/[0-9]+$/);

    // Verify existing supplements are shown in editing mode
    cy.get('[data-testid="manual-supplement-Fragile Handling"]').should(
      'be.checked',
    );

    cy.get('[data-testid="tax-included"]').contains('7,99 €');

    cy.get('[name="delivery.override_price"]').check();
    cy.get('[name="variantName"]').type('Test product');
    cy.get('#variantPriceVAT').type('72');

    // Submit the changes
    cy.get('button[type="submit"]').click();

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/);

    // Verify the updated total includes both supplements
    cy.get('[data-testid="order-total-including-tax"]')
      .find('[data-testid="value"]')
      .contains('€72.00');
  });
});
