context('Delivery (role: admin) with arbitrary price', () => {
  beforeEach(() => {
    cy.loadFixtures('../cypress/fixtures/stores.yml')

    cy.setMockDateTime('2025-04-23 8:30:00')

    cy.login('admin', '12345678')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  it('[legacy] create delivery order with arbitrary price', function () {
    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une nouvelle commande')
      .click()

    cy.get('[data-testid=go-to-legacy-form]').click()

    // Pickup

    cy.newPickupAddress(
      '[data-form="task"]:nth-of-type(1)',
      '23 Avenue Claude Vellefaux, 75010 Paris, France',
      /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
      'Office',
      '+33112121212',
      'John Doe',
    )

    cy.get('#delivery_tasks_0_comments').type('Pickup comments')

    // Dropoff

    cy.newDropoff1Address(
      '[data-form="task"]:nth-of-type(2)',
      '72 Rue Saint-Maur, 75011 Paris, France',
      /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
      'Office',
      '+33112121212',
      'Jane smith',
    )

    cy.get('#delivery_tasks_1_weight').clear()
    cy.get('#delivery_tasks_1_weight').type(2.5)

    cy.get('#delivery_tasks_1_comments').type('Dropoff comments')

    cy.get('#delivery_arbitraryPrice').check()
    cy.get('#delivery_variantName').clear()
    cy.get('#delivery_variantName').type('Test product')
    cy.get('#delivery_variantPrice').clear()
    cy.get('#delivery_variantPrice').type('72')

    cy.get('#delivery-submit').click()

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    cy.get('[data-testid="order_item"]')
      .find('[data-testid="name"]')
      .contains('Test product')
    cy.get('[data-testid="order_item"]')
      .find('[data-testid="total"]')
      .contains('€72.00')
    cy.get('[data-testid="order-total-including-tax"]')
      .find('[data-testid="value"]')
      .contains('€72.00')

    cy.get('[data-testid=delivery-itinerary]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery-itinerary]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')
  })
})
