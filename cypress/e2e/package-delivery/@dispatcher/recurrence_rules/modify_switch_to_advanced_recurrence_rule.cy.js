context('Managing recurrence rules (role: dispatcher)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup([
      'ORM/user_dispatcher.yml',
      'ORM/tags.yml',
      'ORM/store_basic.yml',
    ])

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

  it('switch to advanced recurrence rule', function () {
    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)
    cy.get('a[href*="recurrence-rules"]').click()

    // Recurrence rule page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/recurrence-rules\/[0-9]+$/)
    cy.get('[data-testid="recurrence-container"]').contains(
      'chaque semaine le vendredi, samedi',
    )

    cy.get('[data-testid="recurrence-rule"]').click()

    cy.get('.ant-collapse-header').click()

    cy.get('[data-testid="recurrence-override-rule-checkbox"]').check()

    cy.get('[data-testid="recurrence-override-rule-input"]').clear()
    cy.get('[data-testid="recurrence-override-rule-input"]').type(
      'RRULE:FREQ=WEEKLY;INTERVAL=2;WKST=MO;BYDAY=MO',
    )

    cy.get('[data-testid=save]').click()

    cy.get('button[type="submit"]').click()

    // Recurrence rules list
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/recurrence-rules$/)
    cy.get('[data-testid=recurrence-list-item]')
      .find('[data-testid="recurrence-edit"]')
      .click()

    // Recurrence rule page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/recurrence-rules\/[0-9]+$/)
    cy.get('[data-testid="recurrence-container"]').contains(
      'chaque 2 weeks le lundi',
    )

    cy.get('[data-testid="recurrence-rule"]').click()

    cy.get('.ant-checkbox-input').each(($checkbox, index) => {
      cy.wrap($checkbox).should('not.be.visible')
    })

    cy.get('[data-testid="recurrence-override-rule-checkbox"]').should(
      'be.checked',
    )
    cy.get('[data-testid="recurrence-override-rule-input"]').should(
      'be.visible',
    )
    cy.get('[data-testid="recurrence-override-rule-input"]').should(
      'have.value',
      'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO;WKST=MO',
    )
  })
})
