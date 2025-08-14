import moment from 'moment'

context('Invoicing (role: admin)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup('ORM/package_delivery_orders.yml')
    cy.setEnvVar('PACKAGE_DELIVERY_UI_PRICE_BREAKDOWN_ENABLED', '1')
    cy.login('admin', '12345678')
  })

  afterEach(() => {
    cy.removeEnvVar('PACKAGE_DELIVERY_UI_PRICE_BREAKDOWN_ENABLED')
  })

  it('export data for invoicing in odoo format', function () {
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

    // Select the first organisation
    cy.get('[data-row-key="1"]').within(() => {
      cy.get('.ant-checkbox-input').check()
    })

    cy.get('[data-testid="invoicing.download"]').click()

    cy.get('[value="odoo"]').click()

    cy.intercept('GET', '/api/invoice_line_items/export/odoo?**').as(
      'exportData',
    )

    // Download the file in the standard format
    cy.get('[data-testid="invoicing.download.file"]').click()

    // Verify the exported data
    cy.wait('@exportData').then(interception => {
      const content = interception.response.body

      // split by line
      const lines = content.split('\n').map(line => line.trim())

      // "External ID","Invoice Date",Partner,"Invoice lines / Account","Invoice lines / Product","Invoice lines / Label","Invoice lines / Unit Price","Invoice lines / Quantity"
      expect(lines[0]).to.equal(
        '"External ID","Invoice Date",Partner,"Invoice lines / Account","Invoice lines / Product","Invoice lines / Label","Invoice lines / Unit Price","Invoice lines / Quantity"',
      )
      for (let i = 1; i <= 250; i++) {
        // b776d339-22dd-44f9-acb4-edb0fdac9b20-6b86b27,2025-07-17,Acme,411100,"Livraison à la demande","Livraison à la demande - Supplément de commande: 1 × Plus de 0.00 km: €4.99 - 17/07/2025 (Commande #A1)",4.16,1
        expect(lines[i]).to.match(
          new RegExp(
            `^[a-f0-9-]+,\\d{4}-\\d{2}-\\d{2},Acme,\\d+,"Livraison à la demande","Livraison à la demande - Supplément de commande: 1 × Plus de 0.00 km: €4.99 - \\d{2}/\\d{2}/\\d{4} \\(Commande #A${i}\\)",[0-9]+(\\.[0-9]+)?,1$`,
          ),
        )
      }
    })
  })
})
