describe('Platform catering; employee; onboarding', () => {
  beforeEach(() => {
    cy.loadFixtures(
      'ORM/business_account_employee_invitation_existing_user.yml',
    )
  })

  it('should onboard an employee with an existing user account', () => {
    cy.login('user01', '12345678')

    cy.visit('/invitation/define-password/INVITATION_EMPLOYEE')

    cy.get('.content').should('contain', 'Business Account 1')

    // associate personal account with a business account page
    cy.get('.btn-primary').click()

    cy.get('.alert-success', { timeout: 10000 }).should(
      'contain',
      'Business Account 1',
    )
  })
})
