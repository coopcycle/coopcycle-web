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

  it('accept suggestion in the incident', function () {
    cy.visit('/admin/incidents');

    // Navigate to incident details page
    cy.get('[data-row-key="1"]').within(() => {
      cy.get("a[href='/admin/incidents/1']").click();
    });

    // Verify we're on the incident details page
    cy.urlmatch(/\/admin\/incidents\/1$/);

    // Verify incident details are displayed
    cy.get('[data-testid="page-title"]', { timeout: 10000 }).should(
      'contain.text',
      'Article incorrect',
    );

    // Verify incident header information
    cy.get('[data-testid="incident-status"]')
      .should('be.visible')
      .should('contain.text', 'Ouvert');

    cy.get('[data-testid="incident-priority"]')
      .should('be.visible')
      .should('contain.text', 'Moyen');

    cy.get('[data-testid="incident-reported-by"]')
      .should('be.visible')
      .should('contain.text', 'bob');

    // Verify incident body - description
    cy.get('[data-testid="incident-description"]')
      .should('be.visible')
      .should('contain.text', 'Wrong order details');

    cy.get('[data-testid="task-address-name"]')
      .should('be.visible')
      .should('contain.text', 'Office');

    cy.get('[data-testid="task-address-street"]')
      .should('be.visible')
      .should('contain.text', '72, Rue Saint-Maur, 75011 Paris, France');

    cy.get('[data-testid="task-address-telephone"]')
      .should('be.visible')
      .should('contain.text', '+33112121414');

    // Verify suggestion content is displayed
    cy.get('[data-testid="suggestion-content"]', { timeout: 10000 }).should(
      'be.visible',
    );

    cy.get('[data-testid="suggestion-price-change"]')
      .should('be.visible')
      .should('contain.text', 'Changement de prix suggéré : +2,50 €');

    // Verify old and new price values are displayed
    cy.get('[data-testid="suggestion-old-price-value"]').within(() => {
      cy.get('[data-testid="tax-included-previous"]').should(
        'contain.text',
        'TTC 4,99 €',
      );
    });
    cy.get('[data-testid="suggestion-new-price-value"]').within(() => {
      cy.get('[data-testid="tax-included"]').should(
        'contain.text',
        'TTC 7,49 €',
      );
    });

    // Verify old items cart content
    cy.get('[data-testid="suggestion-old-items"]').within(() => {
      cy.verifyCart([
        {
          name: 'Supplément de commande',
          total: '4,99 €',
          options: [
            {
              name: '1 × Plus de 0.00 km',
              price: '4,99 €',
            },
          ],
        },
      ]);
    });

    // Verify new items cart content
    cy.get('[data-testid="suggestion-new-items"]').within(() => {
      cy.verifyCart([
        {
          name: 'Supplément de commande',
          total: '7,49 €',
          options: [
            {
              name: '1 × Plus de 0.00 km',
              price: '4,99 €',
            },
            {
              name: '1 × Plus de 25.00 kg - €2.50',
              price: '2,50 €',
            },
          ],
        },
      ]);
    });

    // Verify action buttons
    cy.get('[data-testid="suggestion-reject-button"]')
      .should('be.visible')
      .should('contain.text', 'Refuser les suggestions');

    cy.get('[data-testid="suggestion-accept-button"]')
      .should('be.visible')
      .should('contain.text', 'Appliquer les suggestions');

    // Click the accept suggestion button
    cy.get('[data-testid="suggestion-accept-button"]').click();

    // Verify the accepted suggestion event is displayed in the timeline
    cy.get('[data-testid="timeline-event-accepted_suggestion"]', {
      timeout: 10000,
    }).should('be.visible');

    cy.get('[data-testid="timeline-event-accepted_suggestion"]').within(() => {
      cy.get('[data-testid="timeline-event-username"]').should(
        'contain.text',
        'dispatcher',
      );

      cy.get('[data-testid="timeline-event-action"]').should(
        'contain.text',
        'a accepté la suggestion',
      );

      cy.get('[data-testid="timeline-event-time"]').should(
        'contain.text',
        'il y a quelques secondes',
      );

      cy.get('[data-testid="timeline-event-metadata"]').should(
        'contain.text',
        'Changement de prix accepté : +2,50 €',
      );
    });

    // Verify the order total is updated
    cy.get('[data-testid="order-total"]').should('contain.text', '7,49 €');

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

    // Open order history modal
    cy.contains('button', "Afficher l'historique").click();

    cy.get('.ant-modal').within(() => {
      cy.get('.ant-modal-title').should('contain', 'Historique de la commande');

      // Verify the price update event is displayed in the timeline
      cy.get('.ant-timeline-item')
        .contains('.ant-timeline-item-content', 'order:price_updated')
        .parent('.ant-timeline-item')
        .within(() => {
          cy.get('[data-testid="tax-included-previous"]').contains('4,99 €');
          cy.get('[data-testid="tax-included"]').contains('7,49 €');
        });
    });
  });
});
