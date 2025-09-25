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

    // Create a delivery with range supplements first
    cy.visit('/admin/stores');

    cy.get('[data-testid=store_Store_with_Range_Supplements__list_item]')
      .find('.dropdown-toggle')
      .click();

    cy.get('[data-testid=store_Store_with_Range_Supplements__list_item]')
      .contains('Créer une nouvelle commande')
      .click();

    // Create basic delivery
    cy.betaChooseSavedAddressAtPosition(0, 1);
    cy.betaChooseSavedAddressAtPosition(1, 2);
    cy.betaEnterWeightAtPosition(1, 2.5);

    // Add initial range supplement
    cy.get(
      '[data-testid="manual-supplement-range-Waiting time supplement"]',
    ).within(() => {
      cy.get('[data-testid="range-input-field"]').type('{selectall}10');
    });

    cy.get('button[type="submit"]').click();
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/);

    cy.get('[data-testid="order-total-including-tax"]')
      .find('[data-testid="value"]')
      .contains('€14.99');
  });

  afterEach(() => {
    cy.resetMockDateTime();
    cy.removeEnvVar('PACKAGE_DELIVERY_UI_PRICE_BREAKDOWN_ENABLED');
  });

  it('modify delivery range supplement values', function () {
    cy.intercept('POST', '/api/retail_prices/calculate').as(
      'calculateRetailPrice',
    );

    // Go to edit delivery
    cy.get('[data-testid="order-edit"]').click();
    cy.urlmatch(/\/admin\/deliveries\/[0-9]+$/);

    cy.wait('@calculateRetailPrice');

    // Verify current value is loaded
    cy.get('[data-testid="product-option-value-0"]')
      .last()
      .within(() => {
        cy.get('[data-testid="name"]').should(
          'contain',
          '2 × Waiting time supplement',
        );
        cy.get('[data-testid="price"]').should('contain', '10,00 €');
      });
    cy.get(
      '[data-testid="manual-supplement-range-Waiting time supplement"]',
    ).within(() => {
      cy.get('[data-testid="range-input-field"]')
        // quantity * step = 10
        .should('have.value', '10');
      cy.get('[data-testid="range-supplement-price"]').should(
        'contain',
        '5,00 € par 5',
      );
    });

    // Modify the waiting time supplement
    cy.get(
      '[data-testid="manual-supplement-range-Waiting time supplement"]',
    ).within(() => {
      cy.get('[data-testid="range-input-field"]').type('{selectall}20');
    });

    // Verify total price updates
    cy.get('[data-testid="tax-included"]').contains('24,99 €');

    // Save changes
    cy.get('button[type="submit"]').click();

    // Verify changes were saved
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/);

    cy.get('[data-testid="order-item-0"]').within(() => {
      cy.get('[data-testid="adjustment-1"]').within(() => {
        cy.get('[data-testid="name"]').should(
          'contain',
          '4 × Waiting time supplement',
        );
        cy.get('[data-testid="price"]').should('contain', '€20.00');
      });
    });

    cy.get('[data-testid="order-total-including-tax"]')
      .find('[data-testid="value"]')
      .contains('€24.99');
  });
});
