context('Managing recurrence rules (role: admin)', () => {
  beforeEach(() => {
    cy.loadFixtures('../cypress/fixtures/stores.yml')

    // Login
    cy.login('admin', '12345678')

    // Create a delivery order and a recurrence rule
    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une livraison')
      .click()

    // Create delivery page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries\/new$/)

    // Pickup
    cy.betaChooseSavedAddressAtPosition(0, 1)

    // Dropoff
    cy.betaChooseSavedAddressAtPosition(1, 2)

    cy.get(`[name="tasks[1].weight"]`).clear()
    cy.get(`[name="tasks[1].weight"]`).type(2.5)

    cy.get('[data-testid="tax-included"]').contains('4,99 €')

    cy.get('[name="delivery.override_price"]').check()
    cy.get('[name="variantName"]').type('Test product')
    cy.get('#variantPriceVAT').type('72')

    cy.get('[data-testid="recurrence-add"]').click()
    cy.chooseDaysOfTheWeek([5, 6])
    cy.get('[data-testid=save]').click()

    cy.get('button[type="submit"]').click()
  })

  it('modify arbitrary price in recurrence rule', function () {
    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)
    cy.get('a[href*="recurrence-rules"]').click()

    // Recurrence rule page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/recurrence-rules\/[0-9]+$/)
    cy.get('[name="delivery.override_price"]').should('be.checked')
    cy.get('[name="variantName"]').should('have.value', 'Test product')
    cy.get('#variantPriceVAT').should('have.value', '72')

    cy.get('[name="variantName"]').clear()
    cy.get('[name="variantName"]').type('New product')
    cy.get('#variantPriceVAT').clear()
    cy.get('#variantPriceVAT').type('34')

    cy.get('button[type="submit"]').click()

    // Recurrence rules list
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/recurrence-rules$/)
    cy.get('[data-testid=recurrence-list-item]')
      .find('[data-testid="recurrence-edit"]')
      .click()

    // Recurrence rule page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/recurrence-rules\/[0-9]+$/)
    cy.get('[name="variantName"]').should('have.value', 'New product')
    cy.get('#variantPriceVAT').should('have.value', '34')
  })
})
