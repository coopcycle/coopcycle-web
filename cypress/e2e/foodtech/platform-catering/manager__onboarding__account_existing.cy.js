describe('Platform catering; manager; onboarding with an existing user account', () => {
  beforeEach(() => {
    cy.symfonyConsole(
      'coopcycle:fixtures:load -f cypress/fixtures/business_account_manager_invitation_existing_user.yml',
    )
  })

  it('should activate a business account', () => {
    cy.visit('/invitation/define-password/INVITATION_MANAGER')

    // Personal info step
    cy.get('#guest-checkout-password').clear('')
    cy.get('#guest-checkout-password').type('12345678')
    cy.get('button[type="submit"]').click()

    // Company info step
    cy.get('#businessAccountRegistration_businessAccount_legalName').clear('')
    cy.get('#businessAccountRegistration_businessAccount_legalName').type(
      'Business Name Ltd',
    )
    cy.get('#businessAccountRegistration_businessAccount_vatNumber').clear('')
    cy.get('#businessAccountRegistration_businessAccount_vatNumber').type(
      'FR12345678901',
    )
    cy.get('.btn-primary').click()

    // Confirmation page
    cy.url().should('include', '/register/confirmed')
    cy.get('.content').should('contain', 'Félicitations')
  })
})
