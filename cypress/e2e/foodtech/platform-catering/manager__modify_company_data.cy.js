describe('Platform catering; manager; modify company data', () => {
  beforeEach(() => {
    cy.symfonyConsole(
      'coopcycle:fixtures:load -f cypress/fixtures/checkout_platform_catering.yml',
    )
  })

  it("should change company (business account's) name", () => {
    cy.visit('/login')
    cy.login('manager01', '12345678')

    cy.visit('/profile/business-account')

    cy.get('#company_name').clear('')
    cy.get('#company_name').type('NEW NAME')

    cy.get('button[name="company[save]"]').click()

    cy.url().should('include', '/profile/business-account')
    cy.get('.alert-success', { timeout: 30000 }).should('exist')
    cy.get('input[id=company_name]', { timeout: 30000 }).should('have.value', 'NEW NAME')
  })
})
