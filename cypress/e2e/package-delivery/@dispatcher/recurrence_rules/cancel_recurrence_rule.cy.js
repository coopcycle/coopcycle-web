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
    cy.get('[data-testid=save]').click()

    cy.get('button[type="submit"]').click()
  })

  it('cancel recurrence rule', function () {
    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)
    cy.get('a[href*="recurrence-rules"]').click()

    // Recurrence rule page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/recurrence-rules\/[0-9]+$/)
    cy.get('[data-testid="recurrence-container"]').contains(
      'chaque semaine le vendredi, samedi',
    )

    cy.get('[data-testid="recurrence-rule"]').click()
    cy.chooseDaysOfTheWeek([])
    cy.get('[data-testid=save]').click()

    cy.get('button[type="submit"]').click()

    // Recurrence rules list
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/recurrence-rules$/)

    cy.get('.content')
      .contains(
        `Lorsqu\\'une commande récurrente est créée, les règles de récurrence apparaîtront ici`,
      )
      .should('exist')
  })
})
