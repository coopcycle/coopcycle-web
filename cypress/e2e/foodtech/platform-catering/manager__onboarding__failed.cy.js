describe('Platform catering; manager; onboarding; failed', () => {
  beforeEach(() => {
    cy.symfonyConsole(
      'coopcycle:fixtures:load -f cypress/fixtures/business_account_manager_invitation_existing_user.yml',
    )
  })

  it('should fail due to the missing info', () => {
    cy.visit('/invitation/define-password/INVITATION_MANAGER')

    // Personal info step
    cy.get('#guest-checkout-password').clear('')
    cy.get('#guest-checkout-password').type('12345678')
    cy.get('button[type="submit"]').click()

    // Company info step
    cy.get('.btn-primary').click()

    // Staying on the same page due to the missing info
    cy.url().should('include', '/invitation/define-password/INVITATION_MANAGER')
  })
})
