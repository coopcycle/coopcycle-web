describe('Delivery with recurrence rule (role: admin)', () => {
  beforeEach(() => {
    cy.loadFixtures('stores.yml')
    cy.login('admin', '12345678')
  })

  describe('store with time slots', function () {
    it('[beta form] create delivery order and a recurrence rule', function () {
      cy.visit('/admin/stores')

      cy.get('[data-testid=store_Acme__list_item]')
        .find('.dropdown-toggle')
        .click()

      cy.get('[data-testid=store_Acme__list_item]')
        .contains('Créer une livraison')
        .click()

      cy.get('body > div.content > div > div > div > a')
        .contains('click here')
        .click()

      // Pickup
      cy.betaChooseSavedAddressAtPosition(0, 1)

      // Dropoff
      cy.betaChooseSavedAddressAtPosition(1, 2)

      cy.get(`[name="tasks[1].weight"]`).clear()
      cy.get(`[name="tasks[1].weight"]`).type(2.5)

      cy.get('[data-testid="tax-included"]').contains('4,99 €')

      cy.get('[data-testid="recurrence__container"]').find('a').click()
      cy.chooseDaysOfTheWeek([5, 6])
      cy.get('[data-testid=save]').click()

      cy.get('button[type="submit"]').click()

      // list of deliveries page

      // TODO : check for proper redirect when implemented
      // cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries$/)

      cy.urlmatch(/\/admin\/deliveries$/)

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
