import moment from 'moment'

context('Invoicing (role: admin)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup('package_delivery_orders.yml')
    cy.login('admin', '12345678')
  })

  it('show data for invoicing using custom date picker', function () {
    cy.visit('/admin/invoicing')
    cy.get('[data-testid="invoicing.toggleRangePicker"]').click()

    // Choose 2 months (this avoids an edge case when running this test in the last day of the current month)
    const firstDayOfPreviousMonth = moment().startOf('month').subtract(1, 'months').format('YYYY-MM-DD')
    const lastDayOfCurrentMonth = moment().endOf('month').format('YYYY-MM-DD')
    cy.get('.ant-picker-input-active > input').click()
    cy.get('.ant-picker-input-active > input').type(firstDayOfPreviousMonth);
    cy.get(':nth-child(3) > input').click();
    cy.get('.ant-picker-input-active > input').type(`${lastDayOfCurrentMonth}{enter}`);

    cy.get('[data-testid="invoicing.refresh"]').click()

    cy.get('[data-testid="invoicing.organizations"]')
      .contains(/Acme/)
      .should('exist')
  })
})
