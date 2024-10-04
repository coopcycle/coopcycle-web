describe('Platform catering; manager; onboarding with a new user account', () => {
  beforeEach(() => {
    cy.window().then(win => {
      win.sessionStorage.clear()
    })
  })

  it('should activate a business account', () => {
    cy.symfonyConsole(
      'coopcycle:fixtures:load -f cypress/fixtures/business_account_manager_invitation_new_user.yml',
    )

    cy.visit('/invitation/define-password/INVITATION_MANAGER')

    // Personal info step
    cy.get('#businessAccountRegistration_user_username').clear('')
    cy.get('#businessAccountRegistration_user_username').type('manager01')
    cy.get('#businessAccountRegistration_user_plainPassword_first').clear('')
    cy.get('#businessAccountRegistration_user_plainPassword_first').type(
      '12345678',
    )
    cy.get('#businessAccountRegistration_user_plainPassword_second').clear('')
    cy.get('#businessAccountRegistration_user_plainPassword_second').type(
      '12345678',
    )
    cy.get('#businessAccountRegistration_user_legal').check()
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
