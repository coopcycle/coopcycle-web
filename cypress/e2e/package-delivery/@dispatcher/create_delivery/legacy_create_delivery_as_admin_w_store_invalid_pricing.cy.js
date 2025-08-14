context('Delivery (role: admin) for a store with invalid pricing', () => {
  beforeEach(() => {
    cy.loadFixtures('ORM/stores_legacy.yml')

    cy.setMockDateTime('2025-04-23 8:30:00')

    cy.login('admin', '12345678')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  it('[legacy] create delivery for store with invalid pricing', function () {
    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme_with_invalid_pricing__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme_with_invalid_pricing__list_item]')
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

    cy.get('#delivery-submit').click()

    cy.get('.alert-danger', { timeout: 10000 }).should(
      'contain',
      "Le prix de la course n'a pas pu être calculé.",
    )
  })
})
