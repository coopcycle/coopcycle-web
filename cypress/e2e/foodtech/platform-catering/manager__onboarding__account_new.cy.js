describe('Platform catering; manager; onboarding with a new user account', () => {
  beforeEach(() => {
    cy.symfonyConsole(
      'coopcycle:fixtures:load -f cypress/fixtures/business_account_manager_invitation_new_user.yml',
    )
  })

  it('should activate a business account', () => {
    cy.visit('/invitation/define-password/INVITATION_MANAGER')

    // Personal info step
    cy.intercept('GET', '/register/suggest?*').as('getSuggest')

    cy.get('#businessAccountRegistration_user_username').clear('')
    cy.get('#businessAccountRegistration_user_username').type('manager01')

    cy.wait('@getSuggest', { timeout: 5000 })

    cy.get(
      'input[name="businessAccountRegistration[user][plainPassword][first]"]',
    ).type('12345678')
    cy.get(
      'input[name="businessAccountRegistration[user][plainPassword][second]"]',
    ).type('12345678')
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
