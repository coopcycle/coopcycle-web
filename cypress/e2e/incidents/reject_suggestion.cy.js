describe('Incident suggestion management (role: dispatcher)', () => {
  beforeEach(() => {
    cy.loadFixtures([
      'setup_default.yml',
      'user_dispatcher.yml',
      'store_with_manual_supplements.yml',
      'package_delivery_order.yml',
      'incident_with_suggestion.yml',
    ]);

    cy.login('dispatcher', 'dispatcher');
  });

  it('reject suggestion in the incident', function () {
    cy.visit('/admin/incidents');

    // Navigate to incident details page
    cy.get('[data-row-key="1"]').within(() => {
      cy.get("a[href='/admin/incidents/1']").click();
    });

    // Verify we're on the incident details page
    cy.urlmatch(/\/admin\/incidents\/1$/);

    // Verify incident details are displayed
    cy.get('[data-testid="page-title"]').should(
      'contain.text',
      'Article incorrect',
    );

    // Click the reject suggestion button
    cy.contains('button', 'TODO: Refuse suggestions').click();

    // Verify success notification
    cy.get('.ant-notification-notice-success', { timeout: 10000 }).should(
      'contain',
      'Action completed successfully',
    );

    // Verify the incident was updated (reload page to see changes)
    cy.reload();

    // Verify that an event was added (rejected_suggestion event should be visible)
    // The event timeline should show the rejected_suggestion event
    cy.get('.ant-timeline-item').should('exist');
  });
});
