describe('Platform catering; manager; onboarding with a new user account', () => {
  beforeEach(() => {
    cy.loadFixtures('business_account_manager_invitation_new_user.yml')
  })

  it('should show a password mismatch error on the personal info step', () => {
    cy.visit('/invitation/define-password/INVITATION_MANAGER')

    cy.intercept('GET', '/register/suggest?*').as('getSuggest')

    cy.get('#businessAccountRegistration_user_username').clear()
    cy.get('#businessAccountRegistration_user_username').type('manager01')

    cy.wait('@getSuggest', { timeout: 5000 })

    cy.get(
      'input[name="businessAccountRegistration[user][plainPassword][first]"]',
    ).type('12345678')
    cy.get(
      'input[name="businessAccountRegistration[user][plainPassword][second]"]',
    ).type('87654321')
    cy.get('#businessAccountRegistration_user_legal').check()
    cy.get('button[type="submit"]').click()

    cy.contains('Les mots de passe saisis ne correspondent pas.').should(
      'be.visible',
    )
    cy.get('#guest-checkout-password').should('not.exist')
    cy.get('#businessAccountRegistration_businessAccount_legalName').should(
      'not.exist',
    )
    cy.get(
      'input[name="businessAccountRegistration[user][plainPassword][first]"]',
    ).should('exist')
  })

  it('should activate a business account', () => {
    cy.visit('/invitation/define-password/INVITATION_MANAGER')

    // Personal info step
    cy.intercept('GET', '/register/suggest?*').as('getSuggest')

    cy.get('#businessAccountRegistration_user_username').clear()
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
    cy.get('#businessAccountRegistration_businessAccount_legalName').clear()
    cy.get('#businessAccountRegistration_businessAccount_legalName').type(
      'Business Name Ltd',
    )
    cy.get('#businessAccountRegistration_businessAccount_vatNumber').clear()
    cy.get('#businessAccountRegistration_businessAccount_vatNumber').type(
      'FR12345678901',
    )
    cy.get('.btn-primary').click()

    // Confirmation page
    cy.urlmatch('/register/confirmed', 'include')
    cy.get('.content').should('contain', 'Félicitations')
  })
})
