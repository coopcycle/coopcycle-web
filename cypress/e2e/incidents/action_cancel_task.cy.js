describe('Incident management (role: dispatcher)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup([
      'user_dispatcher.yml',
      'user_courier.yml',
      'store_with_task_pricing.yml',
      'package_delivery_order_multi_dropoff.yml',
      'incident.yml',
    ]);

    cy.setMockDateTime('2025-04-23 8:30:00');

    cy.login('dispatcher', 'dispatcher');
  });

  afterEach(() => {
    cy.resetMockDateTime();
  });

  it('cancel task from incident', function () {
    cy.visit('/admin/incidents');

    // verify incident is displayed in the list
    cy.get('[data-row-key="1"]').within(() => {
      // Title column
      cy.get('td:nth-of-type(2)').should('contain.text', 'Not at home');
    });

    // go to incident details page
    cy.get('[data-row-key="1"]').within(() => {
      cy.get("a[href='/admin/incidents/1']").click();
    });

    // Incident details page
    cy.urlmatch(/\/admin\/incidents\/[0-9]+$/);

    // verify incident details are displayed
    cy.get('[data-testid="page-title"]').should('contain.text', 'Not at home');

    // cancel task
    cy.get('[data-testid="take-actions-button"]').click();
    cy.get('[data-testid="cancel-button"]').click();
    cy.get('.ant-popconfirm-buttons > .ant-btn-primary').click();

    // Wait for async work to complete
    cy.consumeMessages();

    // wait for a page to reload before proceeding
    cy.wait(3000);

    // Remove target="_blank" to allow navigation in same window and verify the result
    cy.window().then(win => {
      cy.stub(win, 'open').callsFake(url => {
        win.location.href = url;
      });
    });
    cy.get('[data-testid="view-order"]').click();

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/);

    // Wait for React components to load
    cy.get('[data-testid="delivery-itinerary"]', {
      timeout: 10000,
    }).should('be.visible');

    // verify that the price has been updated
    cy.get('[data-testid="order-total-including-tax"]')
      .find('[data-testid="value"]')
      .contains('€6.99');

    // Open order history modal
    cy.contains('button', "Afficher l'historique").click();

    cy.get('.ant-modal').within(() => {
      cy.get('.ant-modal-title').should('contain', 'Historique de la commande');

      // Verify the price update event is displayed in the timeline
      cy.get('.ant-timeline-item')
        .contains('.ant-timeline-item-content', 'order:price_updated')
        .parent('.ant-timeline-item')
        .within(() => {
          cy.get('[data-testid="tax-included-previous"]').contains('8,99 €');
          cy.get('[data-testid="tax-included"]').contains('6,99 €');
        });
    });
  });
});
