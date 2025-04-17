context('Import deliveries (role: admin)', () => {
  beforeEach(() => {
    const prefix = Cypress.env('COMMAND_PREFIX')

    let cmd =
      'bin/console coopcycle:fixtures:load -s cypress/fixtures/setup.yml -f cypress/fixtures/admin_user.yml -f features/fixtures/ORM/store_w_time_slot_pricing.yml --env test'
    if (prefix) {
      cmd = `${prefix} ${cmd}`
    }

    cy.exec(cmd)

    cy.visit('/login')
    cy.login('admin', '12345678')
  })

  it('imports deliveries', function () {
    cy.visit('/admin/deliveries')

    cy.get('[data-target="#import-deliveries-modal"]').click();
    cy.get('input[type=file]').selectFile('cypress/fixtures/csv/deliveries.csv')
    cy.get('#import-deliveries-modal > .modal-dialog > .modal-content > .modal-footer > .btn-primary').click();

    cy.location('pathname', { timeout: 10000 }).should('eq', '/admin/deliveries')
    cy.location('search').should('include', 'section=imports')

    cy.consumeMessages()

    //TODO: verify imported deliveries
  })
})
