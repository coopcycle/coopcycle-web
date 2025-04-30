import moment from 'moment'

context('Invoicing (role: admin)', () => {
  beforeEach(() => {
    const prefix = Cypress.env('COMMAND_PREFIX')

    let cmd =
      'bin/console coopcycle:fixtures:load -s cypress/fixtures/setup_default.yml -f cypress/fixtures/package_delivery_orders.yml --env test'
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
    const firstDayOfMonth = moment().startOf('month').format('YYYY-MM-DD')
    const lastDayOfMonth = moment().endOf('month').format('YYYY-MM-DD')
    cy.get('.ant-picker-input-active > input').click()
    cy.get('.ant-picker-input-active > input').type(firstDayOfMonth);
    cy.get(':nth-child(3) > input').click();
    cy.get('.ant-picker-input-active > input').type(`${lastDayOfMonth}{enter}`);

    cy.get('[data-testid="invoicing.refresh"]').click()

    cy.get('[data-testid="invoicing.organizations"]')
      .contains(/Acme/, { timeout: 10000 })
      .should('exist')
  })
})
