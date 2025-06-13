import moment from 'moment'

context('Invoicing (role: admin)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup('../cypress/fixtures/package_delivery_orders.yml')
    cy.login('admin', '12345678')
  })

  it('show data for invoicing using custom date picker', function () {
    cy.visit('/admin/invoicing')
    cy.get('[data-testid="invoicing.toggleRangePicker"]').click()

    // Choose 2 months (this avoids an edge case when running this test in the last day of the current month)
    const firstDayOfPreviousMonth = moment()
      .startOf('month')
      .subtract(1, 'months')
      .format('YYYY-MM-DD')
    const lastDayOfCurrentMonth = moment().endOf('month').format('YYYY-MM-DD')
    cy.get('.ant-picker-input-active > input').click()
    cy.get('.ant-picker-input-active > input').type(firstDayOfPreviousMonth)
    cy.get(':nth-child(3) > input').click()
    cy.get('.ant-picker-input-active > input').type(
      `${lastDayOfCurrentMonth}{enter}`,
    )

    cy.get('[data-testid="invoicing.refresh"]').click()

    cy.get('[data-testid="invoicing.organizations"]')
      .contains(/Acme/)
      .should('exist')

    // Expand the first row to see the orders
    cy.get('[aria-label="Développer la ligne"]').first().click()

    // Verify that only the first row is expanded
    cy.get('[aria-label="Réduire la ligne"]').should('have.length', 1)

    // Verify that the orders from A1 to A10 are displayed
    for (let i = 1; i <= 10; i++) {
      cy.get('.ant-table-cell').filter(`:contains("A${i}")`).should('exist')
    }

    // Verify pagination

    // Go to the second page of orders
    cy.get('[data-testid="invoicing.orders.1"]').within(() => {
      cy.get('.ant-pagination-item-2').click()
    })

    // Verify that the orders from A11 to A20 are displayed
    for (let i = 11; i <= 20; i++) {
      cy.get('.ant-table-cell').filter(`:contains("A${i}")`).should('exist')
    }
  })
})
