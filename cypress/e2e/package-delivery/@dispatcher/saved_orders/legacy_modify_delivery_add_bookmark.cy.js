context('Bookmarks (Saved orders) (role: admin)', () => {
  beforeEach(() => {
    cy.loadFixtures('ORM/stores_legacy.yml')

    // Login
    cy.login('admin', '12345678')

    // Create a delivery order
    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une nouvelle commande')
      .click()

    cy.get('[data-testid=go-to-legacy-form]').click()

    // Pickup
    cy.chooseSavedPickupAddress(1)

    cy.get('#delivery_tasks_0_comments').type('Pickup comments')

    // Dropoff
    cy.chooseSavedDropoff1Address(2)

    cy.get('#delivery_tasks_1_weight').clear()
    cy.get('#delivery_tasks_1_weight').type(2.5)

    cy.get('#delivery_tasks_1_comments').type('Dropoff comments')

    cy.get('[data-tax="included"]').contains('4,99 €')

    cy.get('#delivery-submit').click()
  })

  // adding a bookmark to a new order is tested in create_delivery tests

  it('[legacy] should add a bookmark to an existing order', function () {
    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    cy.get('[data-testid="order-edit"]').click()

    // Edit Delivery page
    cy.urlmatch(/\/admin\/deliveries\/[0-9]+$/)

    cy.get('[data-testid=go-to-legacy-form]').click()

    cy.get('#delivery_bookmark').should('not.be.checked')
    cy.get('#delivery_bookmark').check()
    cy.get('#delivery-submit').click()

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
