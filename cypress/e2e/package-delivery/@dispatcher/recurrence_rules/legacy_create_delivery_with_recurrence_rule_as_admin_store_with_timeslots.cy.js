describe('Delivery with recurrence rule (role: admin)', () => {
  beforeEach(() => {
    cy.loadFixtures('../cypress/fixtures/stores.yml')
    cy.login('admin', '12345678')
  })

  describe('store with time slots', function () {
    it('[legacy] create delivery order and a recurrence rule', function () {
      cy.visit('/admin/stores')

      cy.get('[data-testid=store_Acme__list_item]')
        .find('.dropdown-toggle')
        .click()

      cy.get('[data-testid=store_Acme__list_item]')
        .contains('Créer une livraison')
        .click()

      // Pickup
      cy.chooseSavedPickupAddress(1)

      cy.get('#delivery_tasks_0_comments').type('Pickup comments')

      // Dropoff
      cy.chooseSavedDropoff1Address(2)

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
      cy.get('[data-testid="breadcrumb"]')
        .find('[data-testid="order_id"]')
        .click()

      // Order page
      cy.get('a[href*="recurrence-rules"]').click()

      // Recurrence rule page
      cy.urlmatch(/\/admin\/stores\/[0-9]+\/recurrence-rules\/[0-9]+$/)
      cy.get('[data-tax="included"]').contains('4,99 €')
      cy.get('#delivery_form__recurrence__container').contains(
        'chaque semaine le vendredi, samedi',
      )
    })
  })
})
