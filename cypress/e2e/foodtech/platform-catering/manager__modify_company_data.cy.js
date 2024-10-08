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

    cy.get('.alert-success').should('exist')
    cy.get('input[id=company_name]').should('have.value', 'NEW NAME')
  })
})
