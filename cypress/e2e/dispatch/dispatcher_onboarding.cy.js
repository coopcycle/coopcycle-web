describe('Dispatch; dispatcher; onboarding', () => {
  beforeEach(() => {
  })

  // It's not possible for an admin to send an invite to an existing user (and doesn't make sense to do so), so we only support/test the case where the user has to create a new account
  it('should onboard a dispatcher with a new user account', () => {
    cy.symfonyConsole(
      'coopcycle:fixtures:load -f cypress/fixtures/dispatcher_invitation_new_user.yml',
    )

    cy.visit('/invitation/define-password/INVITATION_DISPATCHER')

    // Personal info step
    cy.get('#registration_form_username').clear('')
    cy.get('#registration_form_username').type('dispatch01')
    cy.get('#registration_form_email').clear('')
    cy.get('#registration_form_email').type('dispatch01@demo.coopcycle.org')
    cy.get('#registration_form_plainPassword_first').clear('')
    cy.get('#registration_form_plainPassword_first').type('12345678')
    cy.get('#registration_form_plainPassword_second').clear('')
    cy.get('#registration_form_plainPassword_second').type('12345678')
    cy.get('#registration_form_legal').check()
    cy.get('button[name="registration_form[save]"]').click()

    // Confirmation page
    cy.url().should('include', '/register/confirmed')

    // 'Dispatch' button
    cy.get('.hidden-sm').click()

    // Dispatch dashboard
    cy.url().should('include', '/admin/dashboard')
  })
})
