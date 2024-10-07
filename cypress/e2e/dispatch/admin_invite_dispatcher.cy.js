describe('Dispatch; admin; invite dispatcher', () => {
  beforeEach(() => {
    cy.symfonyConsole('coopcycle:fixtures:load -f cypress/fixtures/base.yml')
  })

  it('should send an invitation to a user', () => {
    cy.visit('/login')
    cy.login('admin', '12345678')

    cy.visit('/admin/users')

    cy.get('.btn-info').click()

    cy.get('#invite_user_email').clear('')
    cy.get('#invite_user_email').type('dispatch01@demo.coopcycle.org')
    cy.get('#invite_user_roles_2').check()
    cy.get('.btn').click()

    cy.get('.alert-success').should(
      'contain',
      'Votre invitation a bien été envoyée ! Vous pouvez à présent éditer les paramètres du nouvel utilisateur.',
    )
  })
})
