context('Delivery (role: dispatcher)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup([
      'user_dispatcher.yml',
      'tags.yml',
      'store_with_price_per_packages_in_order.yml',
    ])
    cy.setEnvVar('PACKAGE_DELIVERY_UI_PRICE_BREAKDOWN_ENABLED', '1');

    cy.setMockDateTime('2025-04-23 8:30:00')

    cy.login('dispatcher', 'dispatcher')
  })

  afterEach(() => {
    cy.resetMockDateTime()
    cy.removeEnvVar('PACKAGE_DELIVERY_UI_PRICE_BREAKDOWN_ENABLED');
  })

  it('create delivery order with multiple dropoff points', function () {
    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une nouvelle commande')
      .click()

    // Create delivery page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries\/new$/)

    // Pickup

    cy.betaChooseSavedAddressAtPosition(0, 1)

    // Dropoff

    cy.betaChooseSavedAddressAtPosition(1, 2)

    cy.get(`[data-testid="form-task-1"]`).within(() => {
      cy.get(
        '[data-testid="/api/packages/1"] > .packages-item__quantity > :nth-child(3)',
      ).click()
    })

    cy.get('[data-testid="add-dropoff-button"]').click()

    cy.betaChooseSavedAddressAtPosition(2, 3)

    cy.get(`[data-testid="form-task-2"]`).within(() => {
      cy.get(
        '[data-testid="/api/packages/1"] > .packages-item__quantity > :nth-child(3)',
      ).click()
    })

    // 2 x SMALL packages
    cy.get('[data-testid="tax-included"]').contains('10,00 €')

    cy.get('button[type="submit"]').click()

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    cy.get('[data-testid="order-total-including-tax"]')
      .find('[data-testid="value"]')
      .contains('€10.00')

    // Wait for React components to load
    cy.get('[data-testid="delivery-itinerary"]', {
      timeout: 10000,
    }).should('be.visible')

    cy.intercept('POST', '/api/retail_prices/calculate').as('calculateRetailPrice')

    cy.get('[data-testid="order-edit"]').click()

    // Edit Delivery page
    cy.urlmatch(/\/admin\/deliveries\/[0-9]+$/)

    //TODO: verify that all the fields are saved correctly

    // cy.betaTaskShouldHaveValue({
    //   taskFormIndex: 0,
    //   addressName: 'Acme',
    //   telephone: '01 12 12 12 10',
    //   contactName: 'Acme',
    //   address: /272,? rue Saint Honoré,? 75001,? Paris/,
    //   date: '23 avril 2025',
    //   timeAfter: '09:30',
    //   timeBefore: '09:40',
    // })
    //
    // cy.betaTaskCollapsedShouldHaveValue({
    //   taskFormIndex: 1,
    //   address: /23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/,
    // })
    //
    // cy.betaTaskCollapsedShouldHaveValue({
    //   taskFormIndex: 2,
    //   address: /72,? Rue Saint-Maur,? 75011,? Paris,? France/,
    // })

    cy.wait('@calculateRetailPrice')

    cy.get('[data-testid="tax-included"]').contains('10,00 €')

    cy.get('[data-testid="apply-new-price"]').should('not.exist');
    cy.get('[data-testid="keep-original-price"]').should('not.exist');
  })
})
