describe('Platform catering; admin; invite manager', () => {
  beforeEach(() => {
    cy.symfonyConsole(
      'coopcycle:fixtures:load -f cypress/fixtures/foodtech.yml',
    )
  })

  it("should send an invitation to a business account's manager", () => {
    cy.visit('/login')
    cy.login('admin', '12345678')

    cy.visit('/admin/restaurants/business-accounts')

    cy.get('[href="/admin/restaurants/business-account/new"]').click()
    cy.get('#company_name').clear('')
    cy.get('#company_name').type('Business01')
    cy.get('#company_managerEmail').clear()
    cy.get('#company_managerEmail').type('manager01@demo.coopcycle.org')
    cy.searchAddress(
      '#company_address',
      '91 rue de rivoli paris',
      /^91,? Rue de Rivoli,? 75001,? Paris,? France/i,
    )
    cy.get('#company_businessRestaurantGroup').select('0')
    cy.get('button[type="submit"]').click()

    cy.url().should('include', '/admin/restaurants/business-account')
    cy.get('.alert-success').should('exist')
    cy.get('table').contains('Business01').should('exist')
  })
})
