context('Delivery (role: dispatcher)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup(['dispatch_dashboard.yml']);

    cy.setMockDateTime('2025-04-23 8:30:00');

    cy.login('dispatcher', 'dispatcher');
  });

  afterEach(() => {
    cy.resetMockDateTime();
  });

  it('navigate from dispatch dashboard to order', function () {
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
      .should('contain', 'Acme›Task A1-1');

    cy.get('[data-testid="order-info"]').within(() => {
      cy.contains('This task is a part of Order A1').should('exist');

      // Remove target="_blank" to allow navigation in same window and verify the result
      cy.window().then(win => {
        cy.stub(win, 'open').callsFake(url => {
          win.location.href = url;
        });
      });

      cy.get('[data-testid="view-order"]')
        .contains('Voir Order A1')
        .should('exist');
      cy.get('[data-testid="view-order"]').click();
    });

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/);

    cy.get('[data-testid="order-title"]')
      .invoke('text')
      .should('contain', 'Commande A1');
  });
});
