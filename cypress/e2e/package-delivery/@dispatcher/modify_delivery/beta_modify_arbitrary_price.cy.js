context('Delivery (role: admin)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup([
      'ORM/user_admin.yml',
      '../features/fixtures/ORM/store_default.yml',
    ])
    cy.setMockDateTime('2025-04-23 8:30:00')
    cy.login('admin', '12345678')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  it('[beta form] update arbitrary price', function () {
    // Create a delivery order with abritrary price

    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une livraison')
      .click()

    // New delivery order page

    cy.get('body > div.content > div > div > div > a')
      .contains('click here')
      .click()

    // Pickup
    cy.betaChooseSavedAddressAtPosition(0, 1)

    // Dropoff
    cy.betaChooseSavedAddressAtPosition(1, 2)

    cy.get('[data-testid="tax-included"]').contains('4,99 €')

    cy.get('[name="delivery.override_price"]').check()
    cy.get('[name="variantName"]').type('Test product')
    cy.get('#variantPriceVAT').type('72')

    cy.get('button[type="submit"]').click()

    // list of deliveries page
    // TODO : check for proper redirect when implemented
    // cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries$/)

    cy.urlmatch(/\/admin\/deliveries$/)

    cy.get('[data-testid=delivery__list_item]')
      .contains(/€72.00/)
      .should('exist')

    cy.get('[data-testid="delivery__list_item"]')
      .find('[data-testid="delivery_id"]')
      .click()

    // Delivery page
    cy.urlmatch(/\/admin\/deliveries\/[0-9]+$/)

    cy.get('body > div.content > div > div > div > a')
      .contains('click here')
      .click()

    cy.get('[data-testid="tax-included-previous"]').contains('72,00 €')

    cy.get('[name="delivery.override_price"]').check()

    cy.get('[name="variantName"]').type('Test product')
    cy.get('#variantPriceVAT').type('34')

    cy.get('s[data-testid="tax-included-previous"]').contains('72,00 €')
    cy.get('[data-testid="tax-included"]').contains('34,00 €')

    cy.get('button[type="submit"]').click()

    // TODO : check for proper redirect when implemented
    // cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries$/)

    cy.urlmatch(/\/admin\/deliveries$/)

    cy.get('[data-testid=delivery__list_item]', { timeout: 10000 })
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/€34.00/)
      .should('exist')
  })
})
