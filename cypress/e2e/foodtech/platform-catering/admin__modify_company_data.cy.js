describe('Platform catering; admin; modify company data', () => {
  beforeEach(() => {
    cy.loadFixtures('ORM/checkout_platform_catering.yml')
  })

  it("should change company (business account's) name", () => {
    cy.login('admin', '12345678')

    cy.visit('/admin/restaurants/business-accounts')

    // First business account in the list
    cy.get('tbody > :nth-child(1) a').click()
    cy.urlmatch(/\/admin\/restaurants\/business-account\/*/)

    cy.intercept('/admin/restaurants/business-account/*').as('submit')
    cy.get('#company_name').clear()
    cy.get('#company_name').type('NEW NAME')
    cy.get('form[name="company"] button[type="submit"]').click()
    cy.wait('@submit', { timeout: 10000 })

    cy.urlmatch(/\/admin\/restaurants\/business-accounts/)
    cy.get('.alert-success', { timeout: 10000 }).should('exist')
    cy.get('tbody').contains('NEW NAME').should('exist')
  })
})
