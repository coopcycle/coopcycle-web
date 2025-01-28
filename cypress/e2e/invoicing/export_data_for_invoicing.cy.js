context('Invoicing (role: admin)', () => {
  beforeEach(() => {
    const prefix = Cypress.env('COMMAND_PREFIX')

    let cmd =
      'bin/console coopcycle:fixtures:load -s cypress/fixtures/setup.yml -f cypress/fixtures/on_demand_delivery_orders.yml --env test'
    if (prefix) {
      cmd = `${prefix} ${cmd}`
    }

    cy.exec(cmd)

    cy.visit('/login')
    cy.login('admin', '12345678')
  })

  it('show data for invoicing using custom date picker', function () {
    cy.visit('/admin/invoicing')

    cy.get('[data-testid="invoicing.toggleRangePicker"]').click()

    // Choose 1 month
    cy.get('.ant-picker-input-active > input').click()
    cy.get(
      ':nth-child(1) > .ant-picker-date-panel > .ant-picker-body > .ant-picker-content > tbody > :nth-child(1) > .ant-picker-cell-start > .ant-picker-cell-inner',
    ).click()
    cy.get(
      ':nth-child(1) > .ant-picker-date-panel > .ant-picker-body > .ant-picker-content > tbody > :nth-child(5) > .ant-picker-cell-end > .ant-picker-cell-inner',
    ).click()

    cy.get('[data-testid="invoicing.refresh"]').click()

    cy.get('[data-testid="invoicing.organizations"]')
      .contains(/Acme/, { timeout: 10000 })
      .should('exist')
  })
})
