context(
  'Delivery (role: dispatcher) and add to bookmarks (saved orders)',
  () => {
    beforeEach(() => {
      cy.loadFixturesWithSetup([
        'user_dispatcher.yml',
        'store_basic.yml',
      ])

      cy.setMockDateTime('2025-04-23 8:30:00')

      cy.login('dispatcher', 'dispatcher')
    })

    afterEach(() => {
      cy.resetMockDateTime()
    })

    it('create delivery order and add to bookmarks (saved orders)', function () {
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

      cy.get('[name="delivery.saved_order"]').check()

      cy.get('button[type="submit"]').click()

      // Order page
      cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

      cy.get('[data-testid="order-total-including-tax"]')
        .find('[data-testid="value"]')
        .contains('€4.99')

      cy.get('[data-testid=delivery-itinerary]')
        .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
        .should('exist')
      cy.get('[data-testid=delivery-itinerary]')
        .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
        .should('exist')

      cy.get('[data-testid="breadcrumb"]').find('[data-testid="store"]').click()

      // Store page

      cy.get('[data-testid="sidenav"]')
        .find('[data-testid="bookmarks"]')
        .click()

      // Saved orders page

      cy.get('[data-testid=delivery__list_item]')
        .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
        .should('exist')
      cy.get('[data-testid=delivery__list_item]')
        .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
        .should('exist')
    })
  },
)
