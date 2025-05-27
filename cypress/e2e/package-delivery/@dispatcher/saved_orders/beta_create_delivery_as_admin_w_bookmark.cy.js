context('Delivery (role: admin) and add to bookmarks (saved orders)', () => {
  beforeEach(() => {
    cy.loadFixtures('stores.yml')

    cy.setMockDateTime('2025-04-23 8:30:00')

    cy.visit('/login')
    cy.login('admin', '12345678')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  it('create delivery order and add to bookmarks (saved orders)', function () {
    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une livraison')
      .click()

    cy.get('body > div.content > div > div > div > a')
      .contains('click here')
      .click()

    // Pickup

    cy.betaChooseSavedAddressAtPosition(0, 1)

    // Dropoff

    cy.betaChooseSavedAddressAtPosition(1, 2)

    cy.get(`[name="tasks[1].weight"]`).type(2.5)

    cy.get('[data-testid="tax-included"]').contains('4,99 €')

    cy.get('#delivery_bookmark').check()

    cy.get('#delivery-submit').click()

    // list of deliveries page
    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/admin\/stores\/[0-9]+\/deliveries$/,
    )
    cy.get('[data-testid=delivery__list_item]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')

    cy.get('[data-testid="breadcrumb"]').find('[data-testid="store"]').click()

    // Store page

    cy.get('[data-testid="sidenav"]').find('[data-testid="bookmarks"]').click()

    // Saved orders page

    cy.get('[data-testid=delivery__list_item]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')
  })
})
