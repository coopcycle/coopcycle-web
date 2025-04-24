context('Import deliveries (role: admin)', () => {
  beforeEach(() => {
    cy.symfonyConsole('coopcycle:fixtures:load -s cypress/fixtures/setup.yml -f cypress/fixtures/admin_user.yml -f features/fixtures/ORM/store_w_time_slot_pricing.yml')
    cy.setMockDateTime('2019-12-12 8:00:00')

    cy.visit('/login')
    cy.login('admin', '12345678')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  it('imports deliveries', function () {
    cy.visit('/admin/deliveries')

    cy.get('[data-target="#import-deliveries-modal"]').click();
    cy.get('input[type=file]').selectFile('cypress/fixtures/csv/deliveries.csv')
    cy.get('#import-deliveries-modal > .modal-dialog > .modal-content > .modal-footer > .btn-primary').click();

    cy.location('pathname', { timeout: 10000 }).should('eq', '/admin/deliveries')
    cy.location('search').should('include', 'section=imports')

    // Allow the import to be processed
    cy.wait(10000)

    //TODO: verify imported deliveries
    cy.get('[data-testid="tab:/admin/deliveries"]').click();
    cy.location('pathname', { timeout: 10000 }).should('eq', '/admin/deliveries')

    // deliveries.csv; line 2; pricing_rule_2
    cy.get('[data-testid=delivery__list_item]')
      .contains(/€2.00/)
      .should('exist')

    // deliveries.csv; line 3; pricing_rule_1 + pricing_rule_2
    cy.get('[data-testid=delivery__list_item]')
      .contains(/€6.99/)
      .should('exist')
  })
})
