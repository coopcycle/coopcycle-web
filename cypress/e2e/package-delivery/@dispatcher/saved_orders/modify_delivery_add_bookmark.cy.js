context('Bookmarks (Saved orders) (role: dispatcher)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup(['user_dispatcher.yml', 'store_basic.yml'])

    // Login
    cy.login('dispatcher', 'dispatcher')

    // Create a delivery order
    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une nouvelle commande')
      .click()

    // Pickup
    cy.betaChooseSavedAddressAtPosition(0, 1)

    // Dropoff
    cy.betaChooseSavedAddressAtPosition(1, 2)

    cy.get('[data-testid="tax-included"]').contains('4,99 €')

    cy.get('button[type="submit"]').click()
  })

  it('should add a bookmark to an existing order', function () {
    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    cy.get('[data-testid="order-edit"]').click()

    // Edit delivery page
    cy.urlmatch(/\/admin\/deliveries\/[0-9]+$/)

    cy.get('[name="delivery.saved_order"]').should('not.be.checked')
    cy.get('[name="delivery.saved_order"]').check()

    cy.get('button[type="submit"]').click()

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    cy.get('[data-testid="breadcrumb"]').find('[data-testid="store"]').click()

    // Store page

    cy.get('[data-testid="sidenav"]').find('[data-testid="bookmarks"]').click()

    // Saved orders page

    cy.get('[data-testid=delivery__list_item]', { timeout: 10000 })
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')
  })
})
