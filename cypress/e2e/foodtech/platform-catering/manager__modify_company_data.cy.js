describe('Platform catering; manager; modify company data', () => {
  beforeEach(() => {
    cy.loadFixtures('checkout_platform_catering.yml')
  })

  it("should change company (business account's) name", () => {
    // Log in from the page itself, so that it is where the login redirects.
    // Landing on the homepage, then visiting the page, its requests could
    // still be running and write the session back without the form's CSRF
    // token, which then rejects the form.
    cy.visit('/profile/business-account')
    cy.login('manager01', '12345678')
    cy.urlmatch('/profile/business-account', 'include')

    cy.get('#company_name').clear()
    cy.get('#company_name').type('NEW NAME')
    cy.get('button[name="company[save]"]').click()

    cy.urlmatch('/profile/business-account', 'include')
    cy.get('.alert-success', { timeout: 10000 }).should('exist')
    cy.get('input[id=company_name]').should('have.value', 'NEW NAME')
  })
})
