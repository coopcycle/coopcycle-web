context('Delivery (role: dispatcher) and add to bookmarks (saved orders)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup(['ORM/user_dispatcher.yml', 'ORM/store_basic.yml'])

    cy.setMockDateTime('2025-04-23 8:30:00')

    cy.visit('/login')
    cy.login('dispatcher', 'dispatcher')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  it('[beta form] create delivery order and add to bookmarks (saved orders)', function () {
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

    cy.get('[name="delivery.saved_order"]').check()

    cy.get('button[type="submit"]').click()

    // list of deliveries page
    // TODO : check for proper redirect when implemented
    // cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries$/)

    cy.urlmatch(/\/admin\/deliveries$/)

    cy.get('[data-testid=delivery__list_item]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')

    // cy.get('[data-testid="breadcrumb"]').find('[data-testid="store"]').click()

    // FIXME when proper redirect is implemented
    cy.get('[href="/admin/stores"]').click()
    cy.get('[data-testid="store_Acme__list_item"] > :nth-child(1) > a').click()

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
