import moment from 'moment'

context('Invoicing (role: admin)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup('../cypress/fixtures/package_delivery_orders.yml')
    cy.setEnvVar('PACKAGE_DELIVERY_UI_PRICE_BREAKDOWN_ENABLED', '0')
    cy.login('admin', '12345678')
  })

  afterEach(() => {
    cy.removeEnvVar('PACKAGE_DELIVERY_UI_PRICE_BREAKDOWN_ENABLED')
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
    cy.get('input[date-range="start"]').click()
    cy.get('input[date-range="start"]').type(firstDayOfPreviousMonth)
    cy.get('input[date-range="end"]').click()
    cy.get('input[date-range="end"]').type(`${lastDayOfCurrentMonth}{enter}`)

    cy.get('[data-testid="invoicing.refresh"]').click()

    cy.get('[data-testid="invoicing.organizations"]')
      .contains(/Acme/)
      .should('exist')

    // Expand the first row to see the orders
    cy.get('[data-row-key="1"]').within(() => {
      cy.get('[aria-label="Développer la ligne"]').first().click()
    })

    // Verify that only the first row is expanded
    cy.get('[aria-label="Réduire la ligne"]').should('have.length', 1)

    // Verify that the orders from A1 to A10 are displayed
    for (let i = 1; i <= 10; i++) {
      cy.get('.ant-table-cell')
        .contains(new RegExp(`^A${i}$`))
        .should('exist')
    }

    // Verify pagination

    // Go to the second page of orders
    cy.get('[data-testid="invoicing.orders.1"]').within(() => {
      cy.get('.ant-pagination-item-2').click()
    })

    // Verify that the orders from A11 to A20 are displayed
    for (let i = 11; i <= 20; i++) {
      cy.get('.ant-table-cell')
        .contains(new RegExp(`^A${i}$`))
        .should('exist')
    }

    // Verify that the first page is not displayed
    for (let i = 1; i <= 10; i++) {
      cy.get(`.ant-table-cell`)
        .contains(new RegExp(`^A${i}$`))
        .should('not.exist')
    }

    // Select the first organisation
    cy.get('[data-row-key="1"]').within(() => {
      cy.get('.ant-checkbox-input').check()
    })

    cy.get('[data-testid="invoicing.download"]').click()

    cy.intercept('GET', '/api/invoice_line_items/export?**').as('exportData')

    // Download the file in the standard format
    cy.get('[data-testid="invoicing.download.file"]').click()

    // Verify the exported data
    cy.wait('@exportData').then(interception => {
      const content = interception.response.body

      // split by line
      const lines = content.split('\n').map(line => line.trim())

      // Organization,Description,"Total products (excl. VAT)",Taxes,"Total products (incl. VAT)"
      expect(lines[0]).to.equal(
        'Organization,Description,"Total products (excl. VAT)",Taxes,"Total products (incl. VAT)"',
      )
      for (let i = 1; i <= 250; i++) {
        // Acme,"Livraison à la demande - 0.00 km - Retrait: Warehouse - Dépôt: Office - 13/06/2025 (Commande #A1)",124.82,24.96,149.78
        expect(lines[i]).to.match(
          new RegExp(
            `^Acme,"Livraison à la demande - [0-9]+(\\.[0-9]+)? km - Retrait: Warehouse - Dépôt: Office - \\d{2}/\\d{2}/\\d{4} \\(Commande #A${i}\\)",[0-9]+(\\.[0-9]+)?,[0-9]+(\\.[0-9]+)?,[0-9]+(\\.[0-9]+)?$`,
          ),
        )
      }
    })
  })
})
