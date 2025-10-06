context('Delivery (role: dispatcher)', () => {
  beforeEach(() => {
    cy.loadFixtures([
      'setup_default.yml',
      'user_admin.yml',
      'tags.yml',
      'store_with_range_supplements.yml',
    ]);
    cy.setEnvVar('PACKAGE_DELIVERY_UI_PRICE_BREAKDOWN_ENABLED', '1');

    cy.setMockDateTime('2025-04-23 8:30:00');

    cy.login('admin', '12345678');

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

    cy.visit('/admin/deliveries/pricing/beta/1');

    // Wait for React components to load
    cy.get('[data-testid="pricing-rule-set-form"]', {
      timeout: 10000,
    }).should('be.visible');

    cy.get('[data-testid="pricing-rule-set-rule-1"]').within(() => {
      // Modify price
      cy.get('[data-testid="rule-price-range-price"]').type('{selectall}8');
    });

    cy.get('button[type="submit"]').click();
  });

  afterEach(() => {
    cy.resetMockDateTime();
    cy.removeEnvVar('PACKAGE_DELIVERY_UI_PRICE_BREAKDOWN_ENABLED');
  });

  it('modify tasks, keep original price', function () {
    cy.visit('/admin/orders/1');

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
        // as a pricing rule has been modified, reset the value to 0, so that a user has to added it again
        .should('have.value', '0');
      cy.root().should(
        'contain',
        'Waiting time supplement',
      );
    });

    cy.betaEnterCommentAtPosition(1, 'New dropoff comments');

    // Verify price is not recalculated
    cy.get('[data-testid="tax-included"]').contains('14,99 €');

    // Save changes
    cy.get('button[type="submit"]').click();

    // Verify changes were saved
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/);

    cy.get('[data-testid="order-item-0"]').within(() => {
      cy.get('[data-testid="adjustment-1"]').within(() => {
        cy.get('[data-testid="name"]').should(
          'contain',
          '2 × Waiting time supplement',
        );
        cy.get('[data-testid="price"]').should('contain', '€10.00');
      });
    });

    cy.get('[data-testid="order-total-including-tax"]')
      .find('[data-testid="value"]')
      .contains('€14.99');
  });
});
