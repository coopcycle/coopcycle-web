describe('Delivery with recurrence rule (role: admin)', () => {
  beforeEach(() => {
    cy.symfonyConsole('coopcycle:fixtures:load -f cypress/fixtures/stores.yml')
    cy.login('admin', '12345678')
  })

  describe('store without time slots', function () {
    it('create delivery order and a recurrence rule', function () {
      cy.visit('/admin/stores')

      cy.get('[data-testid=store_Acme_without_time_slots__list_item]')
        .find('.dropdown-toggle')
        .click()

      cy.get('[data-testid=store_Acme_without_time_slots__list_item]')
        .contains('Créer une livraison')
        .click()

      // Pickup
      cy.chooseSavedPickupAddress(1)

      // set pickup time range to XX:12 - XX:27
      cy.get(
        '#delivery_tasks_0_doneBefore_widget > .ant-picker > .ant-picker-input-active > input',
      ).click()
      cy.get(
        '#delivery_tasks_0_doneBefore_widget > .ant-picker > .ant-picker-input-active > input',
      ).type('{backspace}{backspace}12')
      cy.get('.ant-picker-ok:visible > .ant-btn').click()
      cy.get(
        '#delivery_tasks_0_doneBefore_widget > .ant-picker > .ant-picker-input-active > input',
      ).type('{backspace}{backspace}27')
      cy.get('.ant-picker-ok:visible > .ant-btn').click()

      cy.get('#delivery_tasks_0_comments').type('Pickup comments')

      // Dropoff
      cy.chooseSavedDropoff1Address(2)

      // set dropoff time range to XX:24 - XX:58
      cy.get(
        '#delivery_tasks_1_doneBefore_widget > .ant-picker > .ant-picker-input-active > input',
      ).click()
      cy.get(
        '#delivery_tasks_1_doneBefore_widget > .ant-picker > .ant-picker-input-active > input',
      ).type('{backspace}{backspace}24')
      cy.get('.ant-picker-ok:visible > .ant-btn').click()
      cy.get(
        '#delivery_tasks_1_doneBefore_widget > .ant-picker > .ant-picker-input-active > input',
      ).type('{backspace}{backspace}58')
      cy.get('.ant-picker-ok:visible > .ant-btn').click()

      cy.get('#delivery_tasks_1_weight').clear()
      cy.get('#delivery_tasks_1_weight').type(2.5)

      cy.get('#delivery_tasks_1_comments').type('Dropoff comments')

      cy.get('[data-tax="included"]').contains('4,99 €')

      cy.get('#delivery_form__recurrence__container').find('a').click()
      cy.chooseDaysOfTheWeek([5, 6])
      cy.get('[data-testid=save]').click()

      cy.get('#delivery-submit').click()

      // list of deliveries page
      cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries$/)
      cy.get('[data-testid=delivery__list_item]', { timeout: 10000 })
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

      //pickup time range:
      cy.get(
        '#delivery_tasks_0_doneBefore_widget > .ant-picker > :nth-child(1) > input', { timeout: 10001 }
      ).should($input => {
        const val = $input.val()
        expect(val).to.include(':12')
      })
      cy.get(
        '#delivery_tasks_0_doneBefore_widget > .ant-picker > :nth-child(3) > input', { timeout: 10002 }
      ).should($input => {
        const val = $input.val()
        expect(val).to.include(':27')
      })

      //dropoff time range:
      cy.get(
        '#delivery_tasks_1_doneBefore_widget > .ant-picker > :nth-child(1) > input', { timeout: 10003 }
      ).should($input => {
        const val = $input.val()
        expect(val).to.include(':24')
      })
      cy.get(
        '#delivery_tasks_1_doneBefore_widget > .ant-picker > :nth-child(3) > input', { timeout: 10004 }
      ).should($input => {
        const val = $input.val()
        expect(val).to.include(':58')
      })

      cy.get('[data-testid="breadcrumb"]')
        .find('[data-testid="order_id"]')
        .click()

      // Order page
      cy.get('a[href*="recurrence-rules"]').click()

      // Recurrence rule page
      cy.urlmatch(/\/admin\/stores\/[0-9]+\/recurrence-rules\/[0-9]+$/)

      //pickup time range:
      cy.get(
        '#delivery_tasks_0_doneBefore_widget > .ant-picker > :nth-child(1) > input', { timeout: 10005 }
      ).should($input => {
        const val = $input.val()
        expect(val).to.include(':12')
      })
      cy.get(
        '#delivery_tasks_0_doneBefore_widget > .ant-picker > :nth-child(3) > input', { timeout: 10006 }
      ).should($input => {
        const val = $input.val()
        expect(val).to.include(':27')
      })

      //dropoff time range:
      cy.get(
        '#delivery_tasks_1_doneBefore_widget > .ant-picker > :nth-child(1) > input', { timeout: 10007 }
      ).should($input => {
        const val = $input.val()
        expect(val).to.include(':24')
      })
      cy.get(
        '#delivery_tasks_1_doneBefore_widget > .ant-picker > :nth-child(3) > input', { timeout: 10008 }
      ).should($input => {
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
