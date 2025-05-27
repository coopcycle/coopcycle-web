context('Bookmarks (Saved orders) (role: admin)', () => {
  beforeEach(() => {
    cy.loadFixtures('stores.yml')

    // Login
    cy.login('admin', '12345678')

    // Create a delivery order
    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une livraison')
      .click()

    // Pickup
    cy.chooseSavedPickupAddress(1)

    // Dropoff
    cy.chooseSavedDropoff1Address(2)

    cy.get('#delivery_tasks_1_weight').clear()
    cy.get('#delivery_tasks_1_weight').type(2.5)

    cy.get('[data-tax="included"]').contains('4,99 €')

    cy.get('#delivery-submit').click()
  })

  it('should add a bookmark to an existing order', function () {
    // List of deliveries page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries$/)

    cy.get('[data-testid="delivery__list_item"]')
      .find('[data-testid="delivery_id"]')
      .click()

    // Delivery page

    cy.get('body > div.content > div > div > div > a')
      .contains('click here')
      .click()

    cy.get('#delivery_bookmark').should('not.be.checked')
    cy.get('#delivery_bookmark').check()
    cy.get('#delivery-submit').click()

    // (all) Deliveries page
    cy.urlmatch(/\/admin\/deliveries$/)
    cy.get('[href="/admin/stores"]').click()
    cy.get('[data-testid="store_Acme__list_item"] > :nth-child(1) > a').click()

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
