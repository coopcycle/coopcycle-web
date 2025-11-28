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

  it('cancel a dropoff from an existing order', function () {
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

    cy.validateDeliveryItinerary(
      [
        {
          type: 'Retrait',
          name: 'Warehouse',
          address: /23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/,
        },
        {
          type: 'Dépôt',
          name: 'Office',
          address: /72,? Rue Saint-Maur,? 75011,? Paris/,
        },
        {
          type: 'Dépôt',
          name: 'Office 2',
          address: /72,? Rue Saint-Maur,? 75011,? Paris/,
        },
      ],
      { withTaskLinks: false },
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

    // Cancel one dropoff task
    cy.get('[data-testid="form-task-2"]').within(() => {
      cy.get('[data-testid="toggle-button"]').click();
      cy.get('[data-testid="task-cancel"]').click();
    });

    cy.validateDeliveryItinerary(
      [
        {
          type: 'Retrait',
          name: 'Warehouse',
          address: /23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/,
        },
        {
          type: 'Dépôt',
          name: 'Office',
          address: /72,? Rue Saint-Maur,? 75011,? Paris/,
        },
        {
          type: 'Dépôt',
          status: 'CANCELLED',
          name: 'Office 2',
          address: /72,? Rue Saint-Maur,? 75011,? Paris/,
        },
      ],
      { withTaskLinks: false },
    );

    cy.get('[data-testid="keep-original-price"]', { timeout: 10000 }).should(
      'be.checked',
    );

    cy.get('[data-testid="apply-new-price"]').check();

    cy.get('[data-testid="apply-new-price"]').should('be.checked');
    cy.get('[data-testid="keep-original-price"]').should('not.be.checked');

    cy.get('[data-testid="tax-included"]').contains('6,99 €');

    cy.get('button[type="submit"]').click();

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/);

    // Wait for React components to load
    cy.get('[data-testid="delivery-itinerary"]', {
      timeout: 10000,
    }).should('be.visible');

    cy.validateDeliveryItinerary([
      {
        type: 'Retrait',
        name: 'Warehouse',
        address: /23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/,
      },
      {
        type: 'Dépôt',
        name: 'Office',
        address: /72,? Rue Saint-Maur,? 75011,? Paris/,
      },
      {
        type: 'Dépôt',
        status: 'CANCELLED',
        name: 'Office 2',
        address: /72,? Rue Saint-Maur,? 75011,? Paris/,
      },
    ]);

    cy.get('[data-testid="order-total-including-tax"]')
      .find('[data-testid="value"]')
      .contains('€6.99');
  });
});
