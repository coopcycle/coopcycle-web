context('Delivery (role: admin)', () => {
  beforeEach(() => {
    const prefix = Cypress.env('COMMAND_PREFIX')

    let cmd =
      'bin/console coopcycle:fixtures:load -f cypress/fixtures/stores.yml --env test'
    if (prefix) {
      cmd = `${ prefix } ${ cmd }`
    }

    cy.exec(cmd)

    cy.setMockDateTime('2025-04-23 8:30:00')

    cy.visit('/login')
    cy.login('admin', '12345678')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  it('update price calculated by pricing rules', function () {
    // Create a delivery order with a price calculated by pricing rules

    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une livraison')
      .click()

    // New delivery order page

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

    // list of deliveries page
    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/admin\/stores\/[0-9]+\/deliveries$/,
    )

    cy.get('[data-testid=delivery__list_item]')
      .contains(/€4.99/)
      .should('exist')

    cy.get('[data-testid="delivery__list_item"]')
      .find('[data-testid="delivery_id"]')
      .click()

    // Delivery page
    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/admin\/deliveries\/[0-9]+$/,
    )
    cy.get('#delivery_arbitraryPrice').check()
    cy.get('#delivery_variantName').clear()
    cy.get('#delivery_variantName').type('Test product')
    cy.get('#delivery_variantPrice').clear()
    cy.get('#delivery_variantPrice').type('72')
    cy.get('#delivery-submit').click()

    // list of deliveries page
    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/admin\/deliveries$/,
    )
    cy.get('[data-testid=delivery__list_item]')
      .contains(/€72.00/)
      .should('exist')
  })

  it('update arbitrary price', function () {
    // Create a delivery order with abritrary price

    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une livraison')
      .click()

    // New delivery order page

    // Pickup
    cy.chooseSavedPickupAddress(1)

    cy.get('#delivery_tasks_0_comments').type('Pickup comments')

    // Dropoff
    cy.chooseSavedDropoff1Address(2)

    cy.get('#delivery_tasks_1_weight').clear()
    cy.get('#delivery_tasks_1_weight').type(2.5)

    cy.get('#delivery_tasks_1_comments').type('Dropoff comments')

    cy.get('#delivery_arbitraryPrice').check()
    cy.get('#delivery_variantName').clear()
    cy.get('#delivery_variantName').type('Test product')
    cy.get('#delivery_variantPrice').clear()
    cy.get('#delivery_variantPrice').type('72')

    cy.get('#delivery-submit').click()

    // list of deliveries page
    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/admin\/stores\/[0-9]+\/deliveries$/,
    )

    cy.get('[data-testid=delivery__list_item]')
      .contains(/€72.00/)
      .should('exist')

    cy.get('[data-testid="delivery__list_item"]')
      .find('[data-testid="delivery_id"]')
      .click()

    // Delivery page
    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/admin\/deliveries\/[0-9]+$/,
    )
    cy.get('#delivery_arbitraryPrice').check()
    cy.get('#delivery_variantName').clear()
    cy.get('#delivery_variantName').type('Test product')
    cy.get('#delivery_variantPrice').clear()
    cy.get('#delivery_variantPrice').type('34')
    cy.get('#delivery-submit').click()

    // list of deliveries page
    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/admin\/deliveries$/,
    )
    cy.get('[data-testid=delivery__list_item]')
      .contains(/€34.00/)
      .should('exist')
  })

})
