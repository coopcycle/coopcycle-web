describe('Delivery with recurrence rule (role: admin)', () => {
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

  describe('store with time slots', function () {
    it('create delivery order and a recurrence rule', function () {
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

      cy.get('#delivery_tasks_0_address_name__display').clear()
      cy.get('#delivery_tasks_0_address_name__display').type('Office')

      cy.get('#delivery_tasks_0_address_telephone__display').clear()
      cy.get('#delivery_tasks_0_address_telephone__display').type(
        '+33112121212',
      )

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
      cy.get('#delivery_tasks_1_address_telephone__display').type(
        '+33112121212',
      )

      cy.get('#delivery_tasks_1_address_contactName__display').clear()
      cy.get('#delivery_tasks_1_address_contactName__display').type(
        'Jane smith',
      )

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
      cy.get('a[href*="recurrence-rules"]').click()

      // Recurrence rule page
      cy.location('pathname', { timeout: 10000 }).should(
        'match',
        /\/admin\/stores\/[0-9]+\/recurrence-rules\/[0-9]+$/,
      )
      cy.get('[data-tax="included"]').contains('4,99 €')
      cy.get('#delivery_form__recurrence__container').contains(
        'chaque semaine le vendredi, samedi',
      )
    })
  })

  describe('store without time slots', function () {
    it('create delivery order and a recurrence rule', function () {
      cy.visit('/admin/stores')

      cy.get('[data-testid=store_Store_without_time_slots__list_item]')
        .find('.dropdown-toggle')
        .click()

      cy.get('[data-testid=store_Store_without_time_slots__list_item]')
        .contains('Créer une livraison')
        .click()

      // Pickup

      cy.searchAddress(
        '[data-form="task"]:nth-of-type(1)',
        '23 Avenue Claude Vellefaux, 75010 Paris, France',
        /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
      )

      cy.get('#delivery_tasks_0_address_name__display').clear()
      cy.get('#delivery_tasks_0_address_name__display').type('Office')

      cy.get('#delivery_tasks_0_address_telephone__display').clear()
      cy.get('#delivery_tasks_0_address_telephone__display').type(
        '+33112121212',
      )

      cy.get('#delivery_tasks_0_address_contactName__display').clear()
      cy.get('#delivery_tasks_0_address_contactName__display').type('John Doe')

      // set pickup time range to XX:12 - XX:27
      cy.get('#delivery_tasks_0_doneBefore_widget > .ant-picker > .ant-picker-input-active > input').click();
      cy.get('.ant-picker-content:visible > :nth-child(2) > :nth-child(13) > .ant-picker-time-panel-cell-inner').click();
      cy.get('.ant-picker-ok:visible > .ant-btn').click();
      cy.get('.ant-picker-content:visible > :nth-child(2) > :nth-child(28) > .ant-picker-time-panel-cell-inner').click();
      cy.get('.ant-picker-ok:visible > .ant-btn').click();

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
      cy.get('#delivery_tasks_1_address_telephone__display').type(
        '+33112121212',
      )

      cy.get('#delivery_tasks_1_address_contactName__display').clear()
      cy.get('#delivery_tasks_1_address_contactName__display').type(
        'Jane smith',
      )

      // set dropoff time range to XX:24 - XX:58
      cy.get('#delivery_tasks_1_doneBefore_widget > .ant-picker > .ant-picker-input-active > input').click();
      cy.get('.ant-picker-content:visible > :nth-child(2) > :nth-child(25) > .ant-picker-time-panel-cell-inner').click();
      cy.get('.ant-picker-ok:visible > .ant-btn').click();
      cy.get('.ant-picker-content:visible > :nth-child(2) > :nth-child(59) > .ant-picker-time-panel-cell-inner').click();
      cy.get('.ant-picker-ok:visible > .ant-btn').click();

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
      cy.get('a[href*="recurrence-rules"]').click()

      // Recurrence rule page
      cy.location('pathname', { timeout: 10000 }).should(
        'match',
        /\/admin\/stores\/[0-9]+\/recurrence-rules\/[0-9]+$/,
      )

      //pickup time range:
      cy.get('#delivery_tasks_0_doneBefore_widget > .ant-picker > :nth-child(1) > input').should(($input) => {
        const val = $input.val()
        expect(val).to.include(':12')
      })
      cy.get('#delivery_tasks_0_doneBefore_widget > .ant-picker > :nth-child(3) > input').should(($input) => {
        const val = $input.val()
        expect(val).to.include(':27')
      })

      //dropoff time range:
      cy.get('#delivery_tasks_1_doneBefore_widget > .ant-picker > :nth-child(1) > input').should(($input) => {
        const val = $input.val()
        expect(val).to.include(':24')
      })
      cy.get('#delivery_tasks_1_doneBefore_widget > .ant-picker > :nth-child(3) > input').should(($input) => {
        const val = $input.val()
        expect(val).to.include(':58')
      })

      cy.get('[data-tax="included"]').contains('4,99 €')

      cy.get('#delivery_form__recurrence__container').contains(
        'chaque semaine le vendredi, samedi',
      )
    })
  })
})
