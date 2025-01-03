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

  it('prepare data for invoicing', function () {
    cy.visit('/admin/invoicing')

    //TODO: verify that invoice preparation flow is working
    // after https://github.com/coopcycle/coopcycle/issues/162 is implemented
  })
})
