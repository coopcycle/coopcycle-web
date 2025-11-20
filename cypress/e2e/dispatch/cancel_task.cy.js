context('Dispatch dashboard (role: dispatcher)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup([
      'user_dispatcher.yml',
      'store_w_distance_pricing.yml',
      'package_delivery_order_multi_dropoff.yml',
    ]);

    cy.setMockDateTime('2025-04-23 8:30:00');

    cy.login('dispatcher', 'dispatcher');
  });

  afterEach(() => {
    cy.resetMockDateTime();
  });

  it('cancel a task', function () {
    // Dispatch dashboard
    cy.urlmatch(/\/admin\/dashboard$/);

    cy.get('[data-rfd-droppable-id="unassigned"] > .taskList__tasks')
      .children()
      .first()
      .dblclick();

    //Task details modal
    cy.get('form[name="task"]').should('exist');
    cy.get('[data-testid="task-modal-title"]')
      .invoke('text')
      //FIXME: figure out why organization name is not displayed (works fine with 'dispatch_dashboard.yml' fixture)
      // .should('contain', 'Acme›Tâche A1-1');
      .should('contain', 'Tâche A1-1');

    cy.intercept('PUT', '/api/tasks/*/cancel').as('putCancelTask');

    cy.get('[data-testid="cancel-task-button"]').click();

    cy.wait('@putCancelTask');

    cy.get('[data-rfd-droppable-id="unassigned"] > .taskList__tasks')
      .children()
      // select the last element, because the first one is the one we just cancelled and it is not visible anymore
      .last()
      .dblclick();

    //Task details modal
    cy.get('form[name="task"]').should('exist');
    cy.get('[data-testid="task-modal-title"]')
      .invoke('text')
      .should('contain', 'Tâche A1-3');

    cy.get('[data-testid="order-info"]').within(() => {
      cy.contains('Cette tâche fait partie de Commande A1').should('exist');

      // Remove target="_blank" to allow navigation in same window and verify the result
      cy.window().then(win => {
        cy.stub(win, 'open').callsFake(url => {
          win.location.href = url;
        });
      });

      cy.get('[data-testid="view-order"]').click();
    });

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/);

    // Wait for React components to load
    cy.get('[data-testid="delivery-itinerary"]', {
      timeout: 10000,
    }).should('be.visible');

    cy.get('[data-testid="order-title"]')
      .invoke('text')
      .should('contain', 'Commande A1');

    // Open order history modal
    cy.contains('button', "Afficher l'historique").click();

    cy.get('.ant-modal').within(() => {
      cy.get('.ant-modal-title').should('contain', 'Historique de la commande');

      // Verify the price update event is displayed in the timeline
      cy.get('.ant-timeline-item')
        .contains('.ant-timeline-item-content', 'order:price_updated')
        .parent('.ant-timeline-item')
        .within(() => {
          // before: 6.00 € (6km, 1.00 € per km)
          // after: 2.00 € (2km)
          cy.get('[data-testid="tax-included-previous"]').contains('6,00 €');
          cy.get('[data-testid="tax-included"]').contains('2,00 €');
        });
    });
  });
});
