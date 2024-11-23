context('Managing recurrence rules (role: admin)', () => {
  beforeEach(() => {
    const prefix = Cypress.env('COMMAND_PREFIX')

    let cmd =
      'bin/console coopcycle:fixtures:load -f cypress/fixtures/stores.yml --env test'
    if (prefix) {
      cmd = `${prefix} ${cmd}`
    }

    cy.exec(cmd)

    // Login
    cy.visit('/login')
    cy.login('admin', '12345678')

    // Create a delivery order and a recurrence rule
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

    cy.get('#delivery_form__recurrence__container').find('a').click();
    cy.chooseDaysOfTheWeek([5, 6]);
    cy.get('[data-testid=save]').click();

    cy.get('#delivery-submit').click()
  })

  it('list recurrence rules', function () {
    // List of deliveries page
    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/admin\/stores\/[0-9]+\/deliveries$/,
    )
    cy.get('[data-testid="store"]').click();

    // Store page
    cy.get('[data-testid="recurrence-rules"]').click();

    // Recurrence rules page
    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/admin\/stores\/[0-9]+\/recurrence-rules$/,
    )
    cy.get('[data-testid=recurrence_rule__list_item]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=recurrence_rule__list_item]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')
  })

  it('modify recurrence rule', function () {
    // List of deliveries page
    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/admin\/stores\/[0-9]+\/deliveries$/,
    )

    cy.get('[data-testid="delivery__list_item"]')
      .find('[data-testid="delivery_id"]')
      .click()

    // Delivery page
    cy.get('a[href*="recurrence-rules"]')
      .click()

    // Recurrence rule page
    cy.get('#delivery_form__recurrence__container').contains('chaque semaine le vendredi, samedi')

    cy.get('#delivery_form__recurrence__container').click();
    cy.chooseDaysOfTheWeek([1]);
    cy.get('[data-testid=save]').click();

    cy.get('#delivery-submit').click()

    // Delivery page
    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/admin\/deliveries\/[0-9]+$/,
    )
    cy.get('a[href*="recurrence-rules"]')
      .click()

    // Recurrence rule page
    cy.get('#delivery_form__recurrence__container').contains('chaque semaine le lundi')
  })

  it('cancel recurrence rule', function () {
    // List of deliveries page
    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/admin\/stores\/[0-9]+\/deliveries$/,
    )

    cy.get('[data-testid="delivery__list_item"]')
      .find('[data-testid="delivery_id"]')
      .click()

    // Delivery page
    cy.get('a[href*="recurrence-rules"]')
      .click()

    // Recurrence rule page
    cy.get('#delivery_form__recurrence__container').contains('chaque semaine le vendredi, samedi')

    cy.get('#delivery_form__recurrence__container').click();
    cy.get('.ant-btn-danger > :nth-child(2)').click();
    cy.get('.ant-popover-buttons > .ant-btn-primary > span').click();

    cy.get('#delivery-submit').click()

    // Delivery page
    cy.location('pathname', { timeout: 10000 }).should(
      'match',
      /\/admin\/deliveries\/[0-9]+$/,
    )
    cy.get('a[href*="recurrence-rules"]')
      .click()

    // Recurrence rule page
    cy.get('#delivery_form__recurrence__container').contains('Règle de récurrence annulée')

  })
})
