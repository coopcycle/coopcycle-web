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
    cy.get('[data-testid=go-to-legacy-form]').click()
    cy.get('#delivery_form__recurrence__container').contains(
      'chaque semaine le vendredi, samedi',
    )

    cy.get('#delivery_form__recurrence__container').click()
    cy.get('.ant-btn-danger > :nth-child(2)').click()
    cy.get('.ant-popover-buttons > .ant-btn-primary > span').click()

    cy.get('#delivery-submit').click()

    cy.visit('/admin/orders/1')
    // Order page
    cy.get('a[href*="recurrence-rules"]').click()

    // Recurrence rule page
    cy.get('[data-testid=go-to-legacy-form]').click()
    cy.get('#delivery_form__recurrence__container').contains(
      'Règle de récurrence annulée',
    )
  })
})
