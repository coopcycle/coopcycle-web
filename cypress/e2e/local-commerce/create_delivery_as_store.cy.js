context('Delivery (role: store)', () => {
  beforeEach(() => {
    const prefix = Cypress.env('COMMAND_PREFIX')

    let cmd =
      'bin/console coopcycle:fixtures:load -f cypress/fixtures/stores.yml --env test'
    if (prefix) {
      cmd = `${prefix} ${cmd}`
    }

    cy.exec(cmd)
  })

  it('create delivery', () => {
    cy.intercept('/api/routing/route/*').as('apiRoutingRoute')

    cy.visit('/login')

    cy.login('store_1', 'store_1')

    cy.location('pathname').should('eq', '/dashboard')

    cy.get('a').contains('Créer une livraison').click()

    // Pickup

    cy.searchAddress(
      '[data-form="task"]:nth-of-type(1)',
      '23 Avenue Claude Vellefaux, 75010 Paris, France',
      /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
    )

    cy.get('#delivery_tasks_0_address_name__display').clear()
    cy.get('#delivery_tasks_0_address_name__display').type('Office')

    cy.get('#delivery_tasks_0_address_telephone__display').clear()
    cy.get('#delivery_tasks_0_address_telephone__display').type('+33112121212')

    cy.get('#delivery_tasks_0_address_contactName__display').clear()
    cy.get('#delivery_tasks_0_address_contactName__display').type('John Doe')

    cy.get('#delivery_tasks_0_comments').type('Pickup comments')

    // Dropoff

    cy.searchAddress(
      '[data-form="task"]:nth-of-type(2)',
      '72 Rue Saint-Maur, 75011 Paris, France',
      /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
    )

    cy.get('#delivery_tasks_1_address_name__display').clear()
    cy.get('#delivery_tasks_1_address_name__display').type('Office')

    cy.get('#delivery_tasks_1_address_telephone__display').clear()
    cy.get('#delivery_tasks_1_address_telephone__display').type('+33112121212')

    cy.get('#delivery_tasks_1_address_contactName__display').clear()
    cy.get('#delivery_tasks_1_address_contactName__display').type('Jane smith')

    cy.get('#delivery_tasks_1_weight').clear()
    cy.get('#delivery_tasks_1_weight').type(2.5)

    cy.get('#delivery_tasks_1_comments').type('Dropoff comments')

    cy.wait('@apiRoutingRoute')

    cy.get('#delivery_distance')
      .invoke('text')
      .should('match', /[0-9.]+ Km/)

    cy.get('#delivery-submit').click()

    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/dashboard\/stores\/[0-9]+\/deliveries$/,
    )
    cy.get('[data-testid=delivery__list_item]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/€4.99/)
      .should('exist')
  })

  it('create delivery for store without pricing', () => {
    cy.intercept('/api/routing/route/*').as('apiRoutingRoute')

    cy.visit('/login')

    cy.login('store_no_pricing', 'password')

    cy.location('pathname').should('eq', '/dashboard')

    cy.get('a').contains('Créer une livraison').click()

    // Pickup

    cy.searchAddress(
      '[data-form="task"]:nth-of-type(1)',
      '23 Avenue Claude Vellefaux, 75010 Paris, France',
      /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
    )

    cy.get('#delivery_tasks_0_address_name__display').clear()
    cy.get('#delivery_tasks_0_address_name__display').type('Office')

    cy.get('#delivery_tasks_0_address_telephone__display').clear()
    cy.get('#delivery_tasks_0_address_telephone__display').type('+33112121212')

    cy.get('#delivery_tasks_0_address_contactName__display').clear()
    cy.get('#delivery_tasks_0_address_contactName__display').type('John Doe')

    cy.get('#delivery_tasks_0_comments').type('Pickup comments')

    // Dropoff

    cy.searchAddress(
      '[data-form="task"]:nth-of-type(2)',
      '72 Rue Saint-Maur, 75011 Paris, France',
      /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
    )

    cy.get('#delivery_tasks_1_address_name__display').clear()
    cy.get('#delivery_tasks_1_address_name__display').type('Office')

    cy.get('#delivery_tasks_1_address_telephone__display').clear()
    cy.get('#delivery_tasks_1_address_telephone__display').type('+33112121212')

    cy.get('#delivery_tasks_1_address_contactName__display').clear()
    cy.get('#delivery_tasks_1_address_contactName__display').type('Jane smith')

    cy.get('#delivery_tasks_1_weight').clear()
    cy.get('#delivery_tasks_1_weight').type(2.5)

    cy.get('#delivery_tasks_1_comments').type('Dropoff comments')

    cy.wait('@apiRoutingRoute')

    cy.get('#delivery_distance')
      .invoke('text')
      .should('match', /[0-9.]+ Km/)

    cy.get('#delivery-submit').click()

    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/dashboard\/stores\/[0-9]+\/deliveries$/,
    )
    cy.get('[data-testid=delivery__list_item]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/€0.00/)
      .should('exist')
  })

  it('create delivery for store with invalid pricing', () => {
    cy.intercept('/api/routing/route/*').as('apiRoutingRoute')

    cy.visit('/login')

    cy.login('store_invalid_pricing', 'password')

    cy.location('pathname').should('eq', '/dashboard')

    cy.get('a').contains('Créer une livraison').click()

    // Pickup

    cy.searchAddress(
      '[data-form="task"]:nth-of-type(1)',
      '23 Avenue Claude Vellefaux, 75010 Paris, France',
      /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
    )

    cy.get('#delivery_tasks_0_address_name__display').clear()
    cy.get('#delivery_tasks_0_address_name__display').type('Office')

    cy.get('#delivery_tasks_0_address_telephone__display').clear()
    cy.get('#delivery_tasks_0_address_telephone__display').type('+33112121212')

    cy.get('#delivery_tasks_0_address_contactName__display').clear()
    cy.get('#delivery_tasks_0_address_contactName__display').type('John Doe')

    cy.get('#delivery_tasks_0_comments').type('Pickup comments')

    // Dropoff

    cy.searchAddress(
      '[data-form="task"]:nth-of-type(2)',
      '72 Rue Saint-Maur, 75011 Paris, France',
      /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
    )

    cy.get('#delivery_tasks_1_address_name__display').clear()
    cy.get('#delivery_tasks_1_address_name__display').type('Office')

    cy.get('#delivery_tasks_1_address_telephone__display').clear()
    cy.get('#delivery_tasks_1_address_telephone__display').type('+33112121212')

    cy.get('#delivery_tasks_1_address_contactName__display').clear()
    cy.get('#delivery_tasks_1_address_contactName__display').type('Jane smith')

    cy.get('#delivery_tasks_1_weight').clear()
    cy.get('#delivery_tasks_1_weight').type(2.5)

    cy.get('#delivery_tasks_1_comments').type('Dropoff comments')

    cy.wait('@apiRoutingRoute')

    cy.get('#delivery_distance')
      .invoke('text')
      .should('match', /[0-9.]+ Km/)

    cy.get('#delivery-submit').click()

    cy.get('.alert-danger', { timeout: 10000 }).should(
      'contain',
      "Le prix de la course n'a pas pu être calculé.",
    )
  })
})
