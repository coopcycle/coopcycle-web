context('Delivery (role: admin)', () => {
  beforeEach(() => {
    cy.loadFixtures('ORM/stores_legacy.yml')
    cy.setMockDateTime('2025-04-23 8:30:00')
    cy.login('admin', '12345678')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  it('[legacy] update arbitrary price', function () {
    // Create a delivery order with abritrary price

    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une nouvelle commande')
      .click()

    // New delivery order page

    cy.get('[data-testid=go-to-legacy-form]').click()

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

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    cy.get('[data-testid="order-total-including-tax"]')
      .find('[data-testid="value"]')
      .contains('€72.00')

    cy.get('[data-testid="order-edit"]').click()

    // Edit delivery page
    cy.urlmatch(/\/admin\/deliveries\/[0-9]+$/)

    cy.get('[data-testid=go-to-legacy-form]').click()

    cy.get('#delivery_arbitraryPrice').check()
    cy.get('#delivery_variantName').clear()
    cy.get('#delivery_variantName').type('Test product')
    cy.get('#delivery_variantPrice').clear()
    cy.get('#delivery_variantPrice').type('34')
    cy.get('#delivery-submit').click()

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    cy.get('[data-testid="order-total-including-tax"]')
      .find('[data-testid="value"]')
      .contains('€34.00')
  })
})
