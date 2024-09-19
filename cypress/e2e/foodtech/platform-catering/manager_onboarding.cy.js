describe('Platform catering; manager; onboarding', () => {
  beforeEach(() => {
    cy.window().then(win => {
      win.sessionStorage.clear()
    })
  })

  it('should activate a business account with a new user account', () => {
    cy.symfonyConsole(
      'coopcycle:fixtures:load -f cypress/fixtures/business_account_invitation_new_user.yml',
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
    cy.get('.btn-primary').click()

    cy.url().should('include', '/register/confirmed')
  })

  it('should activate a business account with an existing user account', () => {
    cy.symfonyConsole(
      'coopcycle:fixtures:load -f cypress/fixtures/business_account_invitation_existing_user.yml',
    )

    cy.visit('/invitation/define-password/INVITATION_MANAGER')

    // Personal info step
    cy.get('#guest-checkout-password').clear('')
    cy.get('#guest-checkout-password').type('12345678')
    cy.get('button[type="submit"]').click()

    // Company info step
    cy.get('.btn-primary').click()

    cy.url().should('include', '/register/confirmed')
  })
})
