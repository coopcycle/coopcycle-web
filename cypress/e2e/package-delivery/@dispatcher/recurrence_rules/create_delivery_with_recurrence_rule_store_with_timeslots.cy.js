describe('Delivery with recurrence rule (role: dispatcher)', () => {
  beforeEach(() => {
    cy.loadFixtures('stores_legacy.yml')
    cy.login('dispatcher', 'dispatcher')
  })

  describe('store with time slots', function () {
    it('create delivery order and a recurrence rule', function () {
      cy.visit('/admin/stores')

      cy.get('[data-testid=store_Acme__list_item]')
        .find('.dropdown-toggle')
        .click()

      cy.get('[data-testid=store_Acme__list_item]')
        .contains('Créer une nouvelle commande')
        .click()

      // Create delivery page
      cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries\/new$/)

      // Pickup
      cy.betaChooseSavedAddressAtPosition(0, 1)

      // Dropoff
      cy.betaChooseSavedAddressAtPosition(1, 2)

      cy.betaEnterWeightAtPosition(1, 2.5)

      cy.get('[data-testid="tax-included"]').contains('4,99 €')

      cy.get('[data-testid="recurrence-add"]').click()
      cy.chooseDaysOfTheWeek([5, 6])
      cy.get('[data-testid=save]').click()

      cy.get('button[type="submit"]').click()

      // Order page
      cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

      cy.get('[data-testid="order-total-including-tax"]')
        .find('[data-testid="value"]')
        .contains('€4.99')

      // Wait for React components to load
      cy.get('[data-testid="delivery-itinerary"]', {
        timeout: 10000,
      }).should('be.visible')
      cy.get('[data-testid=delivery-itinerary]')
        .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
        .should('exist')
      cy.get('[data-testid=delivery-itinerary]')
        .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
        .should('exist')

      cy.get('a[href*="recurrence-rules"]').click()

      // Recurrence rule page
      cy.urlmatch(/\/admin\/stores\/[0-9]+\/recurrence-rules\/[0-9]+$/)
      cy.get('[data-testid="tax-included"]').contains('4,99 €')
      cy.get('[data-testid="recurrence-container"]').contains(
        'chaque semaine le vendredi, samedi',
      )
    })
  })
})
