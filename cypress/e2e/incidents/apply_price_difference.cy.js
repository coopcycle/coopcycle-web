describe('Incident management (role: dispatcher)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup([
      'user_dispatcher.yml',
      'store_with_invalid_pricing.yml',
    ]);

    cy.setMockDateTime('2025-04-23 8:30:00');

    cy.login('dispatcher', 'dispatcher');
  });

  afterEach(() => {
    cy.resetMockDateTime();
  });

  it('apply price difference', function () {
    cy.visit('/admin/incidents');

    cy.visit('/admin/stores');

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

    cy.get('.alert-danger', { timeout: 10000 }).should(
      'contain',
      "Le prix n'a pas pu être calculé. Vous pouvez créer la livraison, n'oubliez pas de corriger la règle de prix liée à ce magasin.",
    );

    cy.get('button[type="submit"]').click();

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/);

    cy.visit('/admin/incidents');

    // verify incident is displayed in the list
    cy.get('[data-row-key="1"]').within(() => {
      // Title column
      cy.get('td:nth-of-type(2)').should(
        'contain.text',
        "Commande #1: Le prix de livraison n'a pas pu être calculé. Veuillez l'entrer manuellement et vérifier la tarification.",
      );
    });

    // go to incident details page
    cy.get('[data-row-key="1"]').within(() => {
      cy.get("a[href='/admin/incidents/1']").click();
    });

    // Incident details page
    cy.urlmatch(/\/admin\/incidents\/[0-9]+$/);

    // verify incident details are displayed
    cy.get('[data-testid="page-title"]').should(
      'contain.text',
      "Commande #1: Le prix de livraison n'a pas pu être calculé. Veuillez l'entrer manuellement et vérifier la tarification.",
    );

    // apply price difference
    cy.get('[data-testid="take-actions-button"]').click();
    cy.get('[data-testid="apply-price-diff-button"]').click();
    cy.get('.ant-input-number-input').type('{selectall}15.50');
    cy.get('[data-testid="submit-price-diff-button"]').click();

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

    // Open order history modal
    cy.contains('button', "Afficher l'historique").click();

    cy.get('.ant-modal').within(() => {
      cy.get('.ant-modal-title').should('contain', 'Historique de la commande');

      // Verify the price update event is displayed in the timeline
      cy.get('.ant-timeline-item')
        .contains('.ant-timeline-item-content', 'order:price_updated')
        .parent('.ant-timeline-item')
        .within(() => {
          cy.get('[data-testid="tax-included-previous"]').contains('0,00 €');
          cy.get('[data-testid="tax-included"]').contains('15,50 €');
        });
    });
  });
});
