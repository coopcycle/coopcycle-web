describe('Platform catering; admin; modify company data', () => {
  beforeEach(() => {
    cy.symfonyConsole('coopcycle:fixtures:load -f cypress/fixtures/checkout_platform_catering.yml')
  })

  it("should change company (business account's) name", () => {
    cy.login('admin', '12345678')

    cy.visit('/admin/restaurants/business-accounts')

    // First business account in the list
    cy.get('tbody > :nth-child(1) a').click()
    cy.urlmatch(/\/admin\/restaurants\/business-account\/*/)

    cy.get('#company_name').clear('')
    cy.get('#company_name').type('NEW NAME')
    cy.get('form[name="company"]').submit()

    cy.urlmatch(/\/admin\/restaurants\/business-accounts/)
    cy.get('.alert-success').should('exist')
    cy.get('tbody').contains('NEW NAME').should('exist')
  })
})
