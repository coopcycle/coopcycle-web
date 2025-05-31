context('Delivery (role: dispatcher)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup([
      'ORM/user_dispatcher.yml',
      'ORM/tags.yml',
      'ORM/store_multi_dropoff.yml',
    ])

    cy.setMockDateTime('2025-04-23 8:30:00')

    cy.login('dispatcher', 'dispatcher')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  it('[beta form] create delivery order with multiple dropoff points WITHOUT route optimization', function () {
    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une livraison')
      .click()

    // Edit Delivery page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries\/new$/)
    cy.get('body > div.content > div > div > div > a')
      .contains('click here')
      .click()

    // Edit Delivery page (new)
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries\/new\/beta$/)

    // Pickup

    cy.betaChooseSavedAddressAtPosition(0, 1)

    // Dropoffs

    cy.betaChooseSavedAddressAtPosition(1, 2)
    cy.get(`[data-testid="form-task-1"]`).within(() => {
      // increase time window to have some room for route optimization
      cy.antdSelect('.ant-select[data-testid="select-before"]', '11:00')
    })

    cy.get('[data-testid="add-dropoff-button"]').click()
    cy.get(`[data-testid="form-task-2"]`).within(() => {
      cy.get('[data-testid="toggle-button"]').click()
    })
    cy.betaChooseSavedAddressAtPosition(2, 3)

    // Not optimized point
    cy.get('[data-testid="add-dropoff-button"]').click()
    cy.get(`[data-testid="form-task-3"]`).within(() => {
      cy.get('[data-testid="toggle-button"]').click()
    })
    cy.betaChooseSavedAddressAtPosition(3, 4)

    cy.get('[data-testid="tax-included"]').contains('4,99 €')

    cy.get('button[type="submit"]').click()

    // reject suggestion
    cy.get('[data-testid="delivery-optimization-suggestion-title"]').should(
      'be.visible',
    )
    cy.get('[data-testid="delivery-optimization-suggestion-reject"]').click()

    // list of deliveries page
    // TODO : check for proper redirect when implemented
    // cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries$/)

    cy.urlmatch(/\/admin\/deliveries$/)
    cy.get('[data-testid="delivery__list_item"]')
      .find('[data-testid="delivery_id"]')
      .click()

    // Edit Delivery page
    cy.urlmatch(/\/admin\/deliveries\/[0-9]+$/)
    cy.get('body > div.content > div > div > div > a')
      .contains('click here')
      .click()

    // Edit Delivery page (new)
    cy.urlmatch(/\/admin\/deliveries\/[0-9]+\/beta$/)

    //verify that the points are in the right order

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

    cy.betaTaskCollapsedShouldHaveValue({
      taskFormIndex: 3,
      address: /26,? Av. Mathurin Moreau,? 75019,? Paris,? France/,
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
