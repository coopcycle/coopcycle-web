context('Delivery (role: dispatcher)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup([
      'user_dispatcher.yml',
      'tags.yml',
      'store_default.yml',
    ]);

    cy.setMockDateTime('2025-04-23 8:30:00');

    cy.login('dispatcher', 'dispatcher');
  });

  afterEach(() => {
    cy.resetMockDateTime();
  });

  it('navigate from order to task on dispatch dashboard', function () {
    cy.visit('/admin/stores');

    // List of stores
    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click();

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une nouvelle commande')
      .click();

    // Create delivery page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries\/new$/);

    // Pickup
    cy.betaChooseSavedAddressAtPosition(0, 1);

    // Dropoff
    cy.betaChooseSavedAddressAtPosition(1, 2);

    cy.get('button[type="submit"]').click();

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/);

    // Wait for React components to load
    cy.get('[data-testid="delivery-itinerary"]', {
      timeout: 10000,
    }).should('be.visible');
    // Pickup
    cy.get('[data-testid="delivery-itinerary"] .ant-timeline-item')
      .eq(0)
      .within(() => {
        cy.contains(
          /23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/,
        ).should('exist');

        cy.get(`[data-testid=taskWithNumberLink]`).should(
          'contain',
          'Tâche 1-1',
        );

        cy.get(`[data-testid=taskWithNumberLink]`)
          .should('have.attr', 'href')
          .and(
            'match',
            /^\/admin\/dashboard\/fullscreen\/2025-04-23\?task=\/api\/tasks\/\d+$/,
          );

        // Remove target="_blank" to allow navigation in same window and verify the result
        cy.get(`[data-testid=taskWithNumberLink]`).invoke(
          'removeAttr',
          'target',
        );
        cy.get(`[data-testid=taskWithNumberLink]`).click();
      });

    //Dispatch dashboard
    cy.urlmatch(/\/admin\/dashboard\/fullscreen\/2025-04-23/);

    //Task details modal
    cy.get('form[name="task"]').should('exist');
    cy.get('[data-testid="task-modal-title"]')
      .invoke('text')
      .should('contain', 'Acme›Tâche 1-1');
  });
});
