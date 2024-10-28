context('Delivery (role: admin)', () => {
  beforeEach(() => {
    const prefix = Cypress.env('COMMAND_PREFIX')

    let cmd =
      'bin/console coopcycle:fixtures:load -f cypress/fixtures/stores.yml --env test'
    if (prefix) {
      cmd = `${prefix} ${cmd}`
    }

    cy.exec(cmd)

    cy.visit('/login')
    cy.login('admin', '12345678')
  })

  it('create delivery order', function () {
    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une livraison')
      .click()

    // Pickup

    cy.searchAddress(
      '[data-form="task"]:nth-of-type(1)',
      '23 Avenue Claude Vellefaux, 75010 Paris, France',
      /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
    )

    cy.get('#delivery_tasks_0_address_name__display')
      .clear()
    cy.get('#delivery_tasks_0_address_name__display')
      .type('Office')

    cy.get('#delivery_tasks_0_address_telephone__display').clear()
    cy.get('#delivery_tasks_0_address_telephone__display')
      .type('+33112121212')

    cy.get('#delivery_tasks_0_address_contactName__display').clear()
    cy.get('#delivery_tasks_0_address_contactName__display').type('John Doe')
   
    cy.get('#delivery_tasks_0_comments').type('Pickup comments')

    // Dropoff

    cy.searchAddress(
      '[data-form="task"]:nth-of-type(2)',
      '72 Rue Saint-Maur, 75011 Paris, France',
      /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
    )

    cy.get('#delivery_tasks_1_address_name__display')
    .clear()
    cy.get('#delivery_tasks_1_address_name__display')
      .type('Office')

    cy.get('#delivery_tasks_1_address_telephone__display').clear()
    cy.get('#delivery_tasks_1_address_telephone__display')
      .type('+33112121212')

    cy.get('#delivery_tasks_1_address_contactName__display').clear()
    cy.get('#delivery_tasks_1_address_contactName__display').type('Jane smith')

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
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')

    cy.get('[data-testid="delivery__list_item"]')
      .find('[data-testid="delivery_id"]')
      .click()

    // Delivery page
    //TODO: verify that all input data is saved correctly
    cy.get('[data-testid="breadcrumb"]')
      .find('[data-testid="order_id"]')
      .should('exist')

    cy.get('[data-testid="breadcrumb"]')
      .find('[data-testid="order_id"]')
      .click()

    // Order page
    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/admin\/orders\/[0-9]+$/,
    )

    cy.get('[data-testid="order_item"]')
      .find('[data-testid="total"]')
      .contains('€4.99')
  })

  it('create delivery order with arbitrary price', function () {
    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une livraison')
      .click()

    // Pickup

    cy.searchAddress(
      '[data-form="task"]:nth-of-type(1)',
      '23 Avenue Claude Vellefaux, 75010 Paris, France',
      /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
    )

    cy.get('#delivery_tasks_0_address_name__display')
    .clear()
    cy.get('#delivery_tasks_0_address_name__display')
      .type('Office')

    cy.get('#delivery_tasks_0_address_telephone__display').clear()
    cy.get('#delivery_tasks_0_address_telephone__display')
      .type('+33112121212')

    cy.get('#delivery_tasks_0_address_contactName__display').clear()
    cy.get('#delivery_tasks_0_address_contactName__display').type('John Doe')

    cy.get('#delivery_tasks_0_comments').type('Pickup comments')

    // Dropoff

    cy.searchAddress(
      '[data-form="task"]:nth-of-type(2)',
      '72 Rue Saint-Maur, 75011 Paris, France',
      /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
    )

    cy.get('#delivery_tasks_1_address_name__display')
    .clear()
    cy.get('#delivery_tasks_1_address_name__display')
      .type('Office')

    cy.get('#delivery_tasks_1_address_telephone__display').clear()
    cy.get('#delivery_tasks_1_address_telephone__display')
      .type('+33112121212')

    cy.get('#delivery_tasks_1_address_contactName__display').clear()
    cy.get('#delivery_tasks_1_address_contactName__display').type('Jane smith')

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
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')

    cy.get('[data-testid="delivery__list_item"]')
      .find('[data-testid="delivery_id"]')
      .click()

    // Delivery page
    cy.get('[data-testid="breadcrumb"]')
      .find('[data-testid="order_id"]')
      .should('exist')

    cy.get('[data-testid="breadcrumb"]')
      .find('[data-testid="order_id"]')
      .click()

    // Order page
    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/admin\/orders\/[0-9]+$/,
    )
    cy.get('[data-testid="order_item"]')
      .find('[data-testid="name"]')
      .contains('Test product')
    cy.get('[data-testid="order_item"]')
      .find('[data-testid="total"]')
      .contains('€72.00')
  })

  it('create delivery order and a subscription', function () {
    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une livraison')
      .click()

    // Pickup

    cy.searchAddress(
      '[data-form="task"]:nth-of-type(1)',
      '23 Avenue Claude Vellefaux, 75010 Paris, France',
      /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
    )

    cy.get('#delivery_tasks_0_address_name__display')
    .clear()
    cy.get('#delivery_tasks_0_address_name__display')
      .type('Office')

    cy.get('#delivery_tasks_0_address_telephone__display').clear()
    cy.get('#delivery_tasks_0_address_telephone__display')
      .type('+33112121212')

    cy.get('#delivery_tasks_0_address_contactName__display').clear()
    cy.get('#delivery_tasks_0_address_contactName__display').type('John Doe')

    cy.get('#delivery_tasks_0_comments').type('Pickup comments')

    // Dropoff

    cy.searchAddress(
      '[data-form="task"]:nth-of-type(2)',
      '72 Rue Saint-Maur, 75011 Paris, France',
      /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
    )

    cy.get('#delivery_tasks_1_address_name__display')
    .clear()
    cy.get('#delivery_tasks_1_address_name__display')
      .type('Office')

    cy.get('#delivery_tasks_1_address_telephone__display').clear()
    cy.get('#delivery_tasks_1_address_telephone__display')
      .type('+33112121212')

    cy.get('#delivery_tasks_1_address_contactName__display').clear()
    cy.get('#delivery_tasks_1_address_contactName__display').type('Jane smith')

    cy.get('#delivery_tasks_1_weight').clear()
    cy.get('#delivery_tasks_1_weight').type(2.5)

    cy.get('#delivery_tasks_1_comments').type('Dropoff comments')

    cy.get('[data-tax="included"]').contains('4,99 €')

    cy.get('#delivery_form__recurrence__container').find('a').click()
    cy.chooseDaysOfTheWeek([5, 6])
    cy.get('[data-testid=save]').click()

    cy.get('#delivery-submit').click()

    // list of deliveries page
    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/admin\/stores\/[0-9]+\/deliveries$/,
    )
    cy.get('[data-testid=delivery__list_item]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')

    cy.get('[data-testid="delivery__list_item"]')
      .find('[data-testid="delivery_id"]')
      .click()

    // Delivery page
    cy.get('#delivery_form__recurrence__container').should('not.exist')
    cy.get('a[href*="subscriptions"]').click()

    // Subscription page
    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/admin\/stores\/[0-9]+\/subscriptions\/[0-9]+$/,
    )
    cy.get('[data-tax="included"]').contains('4,99 €')
    cy.get('#delivery_form__recurrence__container').contains(
      'chaque semaine le vendredi, samedi',
    )
  })

  it('create delivery order and add to bookmarks (saved orders)', function () {
    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une livraison')
      .click()

    // Pickup

    cy.searchAddress(
      '[data-form="task"]:nth-of-type(1)',
      '23 Avenue Claude Vellefaux, 75010 Paris, France',
      /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
    )

    cy.get('#delivery_tasks_0_address_name__display')
    .clear()
    cy.get('#delivery_tasks_0_address_name__display')
      .type('Office')

    cy.get('#delivery_tasks_0_address_telephone__display').clear()
    cy.get('#delivery_tasks_0_address_telephone__display')
      .type('+33112121212')

    cy.get('#delivery_tasks_0_address_contactName__display').clear()
    cy.get('#delivery_tasks_0_address_contactName__display').type('John Doe')

    cy.get('#delivery_tasks_0_comments').type('Pickup comments')

    // Dropoff

    cy.searchAddress(
      '[data-form="task"]:nth-of-type(2)',
      '72 Rue Saint-Maur, 75011 Paris, France',
      /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
    )

    cy.get('#delivery_tasks_1_address_name__display')
    .clear()
    cy.get('#delivery_tasks_1_address_name__display')
      .type('Office')

    cy.get('#delivery_tasks_1_address_telephone__display').clear()
    cy.get('#delivery_tasks_1_address_telephone__display')
      .type('+33112121212')

    cy.get('#delivery_tasks_1_address_contactName__display').clear()
    cy.get('#delivery_tasks_1_address_contactName__display').type('Jane smith')

    cy.get('#delivery_tasks_1_weight').clear()
    cy.get('#delivery_tasks_1_weight').type(2.5)

    cy.get('#delivery_tasks_1_comments').type('Dropoff comments')

    cy.get('[data-tax="included"]').contains('4,99 €')

    cy.get('#delivery_bookmark').check()

    cy.get('#delivery-submit').click()

    // list of deliveries page
    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/admin\/stores\/[0-9]+\/deliveries$/,
    )
    cy.get('[data-testid=delivery__list_item]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')

    cy.get('[data-testid="breadcrumb"]').find('[data-testid="store"]').click()

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

  it('create delivery for store with createOrders disabled', function () {
    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Store_with_createOrders_disabled__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Store_with_createOrders_disabled__list_item]')
      .contains('Créer une livraison')
      .click()

    // Pickup

    cy.searchAddress(
      '[data-form="task"]:nth-of-type(1)',
      '23 Avenue Claude Vellefaux, 75010 Paris, France',
      /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
    )

    cy.get('#delivery_tasks_0_address_name__display')
    .clear()
    cy.get('#delivery_tasks_0_address_name__display')
      .type('Office')

    cy.get('#delivery_tasks_0_address_telephone__display').clear()
    cy.get('#delivery_tasks_0_address_telephone__display')
      .type('+33112121212')

    cy.get('#delivery_tasks_0_address_contactName__display').clear()
    cy.get('#delivery_tasks_0_address_contactName__display').type('John Doe')

    cy.get('#delivery_tasks_0_comments').type('Pickup comments')

    // Dropoff

    cy.searchAddress(
      '[data-form="task"]:nth-of-type(2)',
      '72 Rue Saint-Maur, 75011 Paris, France',
      /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
    )

    cy.get('#delivery_tasks_1_address_name__display')
    .clear()
    cy.get('#delivery_tasks_1_address_name__display')
      .type('Office')

    cy.get('#delivery_tasks_1_address_telephone__display').clear()
    cy.get('#delivery_tasks_1_address_telephone__display')
      .type('+33112121212')

    cy.get('#delivery_tasks_1_address_contactName__display').clear()
    cy.get('#delivery_tasks_1_address_contactName__display').type('Jane smith')

    cy.get('#delivery_tasks_1_weight').clear()
    cy.get('#delivery_tasks_1_weight').type(2.5)

    cy.get('#delivery_tasks_1_comments').type('Dropoff comments')

    cy.get('#delivery-submit').click()

    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/admin\/stores\/[0-9]+\/deliveries$/,
    )
    cy.get('[data-testid=delivery__list_item]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')
  })
})
