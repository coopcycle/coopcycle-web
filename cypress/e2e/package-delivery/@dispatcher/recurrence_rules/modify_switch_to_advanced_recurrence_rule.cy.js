import moment from 'moment'

context('Managing recurrence rules (role: admin)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup([
      'ORM/user_admin.yml',
      'ORM/tags.yml',
      'ORM/store_basic.yml',
    ])

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

    cy.visit('/admin/dashboard')

    // Verify the main order is created
    cy.get('[data-task-id]').should('have.length', 2)

    // next week; Monday -> skip
    cy.visit(
      `/admin/dashboard/fullscreen/${moment()
        .day(1)
        .add(moment().day() <= 1 ? 0 : 1, 'weeks')
        .format('YYYY-MM-DD')}?nav=on`,
    )

    // allow recurrence rules to be checked
    cy.wait(5000)
    // FIXME; we need to refresh the page because websockets are not working in tests currently
    cy.reload()

    // Verify the recurrence order is NOT created
    cy.get('[data-task-id]').should('have.length', 0)

    // a week after next week; Monday -> create an order
    cy.visit(
      `/admin/dashboard/fullscreen/${moment()
        .day(1)
        .add(moment().day() <= 1 ? 1 : 2, 'weeks')
        .format('YYYY-MM-DD')}?nav=on`,
    )

    // allow recurrence rules to be checked
    cy.wait(5000)
    // FIXME; we need to refresh the page because websockets are not working in tests currently
    cy.reload()

    // Verify the recurrence order is created
    cy.get('[data-task-id]').should('have.length', 2)
  })
})
