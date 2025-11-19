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

  it('view order history', function () {
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

    // Open order history modal
    cy.contains('button', "Afficher l'historique").click();

    // Verify history events
    cy.get('.ant-modal').within(() => {
      cy.get('.ant-modal-title').should('contain', 'Historique de la commande');

      // Verify order:created event exists
      cy.get('.ant-timeline-item')
        .contains('.ant-timeline-item-content', 'order:created')
        .should('exist');

      // Verify order:state_changed event with "new" state exists
      cy.get('.ant-timeline-item')
        .contains('.ant-timeline-item-content', 'order:state_changed')
        .parent('.ant-timeline-item')
        .within(() => {
          cy.get('.ant-timeline-item-content').should('contain', 'new');
        });

      // Verify task:created for Tâche 1-1 Retrait: Warehouse exists
      cy.get('.ant-timeline-item')
        .contains('[data-testid="taskWithNumberLink"]', 'Tâche 1-1')
        .parents('.ant-timeline-item')
        .within(() => {
          cy.get('.ant-timeline-item-content').should(
            'contain',
            'task:created',
          );
          cy.get(`[data-testid=taskWithNumberLink]`)
            .should('have.attr', 'href')
            .and(
              'match',
              /^\/admin\/dashboard\/fullscreen\/2025-04-23\?task=\/api\/tasks\/\d+$/,
            );
          cy.get('.ant-timeline-item-content').should('contain', 'Retrait');
          cy.get('.ant-timeline-item-content').should('contain', 'Warehouse');
        });

      // Verify task:created for Tâche 1-2 Dépôt: Office exists
      cy.get('.ant-timeline-item')
        .contains('[data-testid="taskWithNumberLink"]', 'Tâche 1-2')
        .parents('.ant-timeline-item')
        .within(() => {
          cy.get('.ant-timeline-item-content').should(
            'contain',
            'task:created',
          );
          cy.get(`[data-testid=taskWithNumberLink]`)
            .should('have.attr', 'href')
            .and(
              'match',
              /^\/admin\/dashboard\/fullscreen\/2025-04-23\?task=\/api\/tasks\/\d+$/,
            );
          cy.get('.ant-timeline-item-content').should('contain', 'Dépôt');
          cy.get('.ant-timeline-item-content').should('contain', 'Office');
        });
    });
  });
});
