describe('Platform catering; employee; onboarding', () => {
  beforeEach(() => {
  })

  it('should onboard an employee with a new user account', () => {
    cy.symfonyConsole(
      'coopcycle:fixtures:load -f cypress/fixtures/business_account_employee_invitation_new_user.yml',
    )

    cy.visit('/invitation/define-password/INVITATION_EMPLOYEE')

    cy.get('.alert-info').should('contain', 'manager01')

    // Personal info step
    cy.get('#registration_form_username').clear('')
    cy.get('#registration_form_username').type('employee01')
    cy.get('#registration_form_email').clear()
    cy.get('#registration_form_email').type('employee01@demo.coopcycle.or')
    cy.get('#registration_form_plainPassword_first').clear()
    cy.get('#registration_form_plainPassword_first').type('12345678')
    cy.get('#registration_form_plainPassword_second').clear('')
    cy.get('#registration_form_plainPassword_second').type('12345678')
    cy.get('#registration_form_legal').check()
    cy.get('button[name="registration_form[save]"]').click()

    // Confirmation page
    cy.url().should('include', '/register/confirmed')
    cy.get('.content').should('contain', 'Félicitations')
  })

  it('should onboard an employee with an existing user account', () => {
    cy.symfonyConsole(
      'coopcycle:fixtures:load -f cypress/fixtures/business_account_employee_invitation_existing_user.yml',
    )

    cy.visit('/login')
    cy.login('user01', '12345678')

    cy.visit('/invitation/define-password/INVITATION_EMPLOYEE')

    cy.get('.content').should('contain', 'Business Account 1')

    // associate personal account with a business account page
    cy.get('.btn-primary').click()

    cy.get('.alert-success').should('contain', 'Business Account 1')
  })
})
