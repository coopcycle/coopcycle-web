context('Managing recurrence rules (role: dispatcher)', () => {
  beforeEach(() => {
    cy.loadFixtures('stores_legacy.yml')

    // Login
    cy.login('dispatcher', 'dispatcher')

    // Create a delivery order and a recurrence rule
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

    // The rule is prepared in advance, for a short term contract
    cy.get('input[data-testid="recurrence-start-date"]').type('2030-01-15{enter}')
    cy.get('input[data-testid="recurrence-end-date"]').type('2030-06-30{enter}')

    cy.get('[data-testid=save]').click()

    cy.get('button[type="submit"]').click()
  })

  it('list recurrence rules', function () {
    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    cy.get('[data-testid="breadcrumb"]').find('[data-testid="store"]').click()

    // Store page
    cy.urlmatch(/\/admin\/stores\/[0-9]+$/)
    cy.get('[data-testid="recurrence-rules"]').click()

    // Recurrence rules page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/recurrence-rules$/)
    cy.get('[data-testid=recurrence-list-item]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=recurrence-list-item]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')

    // The dates are formatted by PHP intl, which only has the ICU data for English
    // in the Docker images, so they are formatted the US way (1/15/30)
    cy.get('[data-testid=recurrence-list-item]')
      .contains(/15\/01\/2030|1\/15\/30/)
      .should('exist')
    cy.get('[data-testid=recurrence-list-item]')
      .contains(/30\/06\/2030|6\/30\/30/)
      .should('exist')
  })
})
