context('Delivery (role: admin)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup([
      'ORM/user_admin.yml',
      'ORM/tags.yml',
      'ORM/store_multi_dropoff.yml',
    ])

    cy.setMockDateTime('2025-04-23 8:30:00')

    cy.login('admin', '12345678')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  it('[beta form] create delivery order with multiple dropoff points', function () {
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

    cy.get('[data-testid="add-dropoff-button"]').click()

    cy.get(`[data-testid="form-task-2"]`).within(() => {
      cy.get('[data-testid="toggle-button"]').click()
    })

    cy.betaChooseSavedAddressAtPosition(2, 3)

    cy.get('[data-testid="tax-included"]').contains('4,99 €')

    cy.get('button[type="submit"]').click()

    // list of deliveries page
    // TODO : check for proper redirect when implemented
    // cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries$/)

    cy.urlmatch(/\/admin\/deliveries$/)

    cy.get('[data-testid=delivery__list_item]', { timeout: 10000 })
      .contains(/272,? rue Saint Honoré,? 75001,? Paris/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/€4.99/)
      .should('exist')

    cy.get('[data-testid="delivery__list_item"]')
      .find('[data-testid="delivery_id"]')
      .click()

    // Edit Delivery page

    cy.get('body > div.content > div > div > div > a')
      .contains('click here')
      .click()

    //verify that all the fields are saved correctly

    cy.betaTaskShouldHaveValue({
      taskFormIndex: 0,
      addressName: 'Acme',
      telephone: '01 12 12 12 10',
      contactName: 'Acme',
      address: /272,? rue Saint Honoré,? 75001,? Paris/,
      date: '23 avril 2025',
      timeAfter: '09:30',
      timeBefore: '09:40',
    })

    cy.betaTaskCollapsedShouldHaveValue({
      taskFormIndex: 1,
      address: /23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/,
    })

    cy.betaTaskCollapsedShouldHaveValue({
      taskFormIndex: 2,
      address: /72,? Rue Saint-Maur,? 75011,? Paris,? France/,
    })

    cy.get('[data-testid="tax-included-previous"]').contains('4,99 €')

    cy.get('[data-testid="breadcrumb"]')
      .find('[data-testid="order_id"]')
      .should('exist')

    cy.get('[data-testid="breadcrumb"]')
      .find('[data-testid="order_id"]')
      .click()

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    cy.get('[data-testid="order_item"]')
      .find('[data-testid="total"]')
      .contains('€4.99')
  })
})
