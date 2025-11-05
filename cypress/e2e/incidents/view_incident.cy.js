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

  it('view incident', function () {
    cy.visit('/admin/incidents');

    //TODO: verify empty state

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

      // verify extra info can be opened
      // expand the first row
      //FIXME: why not localised?
      cy.get('[aria-label="Expand row"]').first().click();
      // cy.get('[aria-label="Développer la ligne"]').first().click();

      // verify that only the first row is expanded
      //FIXME: why not localised?
      cy.get('[aria-label="Collapse row"]').should('have.length', 1);
      // cy.get('[aria-label="Réduire la ligne"]').should('have.length', 1);
    });

    // verify incident summary is displayed
    cy.get("div[data-testid='task-type']", { timeout: 10000 })
      .should('be.visible')
      .should('contain.text', 'Type: pickup');

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
  });
});
