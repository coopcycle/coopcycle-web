describe('Platform catering; manager; modify company data', () => {
  beforeEach(() => {
    cy.loadFixtures('../cypress/fixtures/checkout_platform_catering.yml')
  })

  it("should change company (business account's) name", () => {
    cy.login('manager01', '12345678')

    cy.visit('/profile/business-account')

    cy.get('#company_name').clear()
    cy.get('#company_name').type('NEW NAME')
    cy.get('button[name="company[save]"]').click()

    cy.urlmatch('/profile/business-account', 'include')
    cy.get('.alert-success', { timeout: 10000 }).should('exist')
    cy.get('input[id=company_name]').should('have.value', 'NEW NAME')
  })
})
