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

    //TODO: verify incident is displayed in the list
    //TODO: verify extra info can be opened
    //TODO: go to incident details page and verify it's loaded
  });
});
