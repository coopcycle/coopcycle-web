describe('Dispatch; admin; invite dispatcher', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup('user_admin.yml')
    cy.login('admin', '12345678')
  })

  it('should send an invitation to a user', () => {
    cy.visit('/admin/users')
    cy.get('.btn-info').click()

    // user invite page
    cy.urlmatch(/\/admin\/users\/invite$/)

    cy.get('#invite_user_email').clear()
    cy.get('#invite_user_email').type('dispatch01@demo.coopcycle.org')
    cy.get('#invite_user_roles_2').check()

    cy.intercept('/admin/users/invite').as('submit')
    cy.get('button[type="submit"]').click()
    cy.wait('@submit', { timeout: 10000 })

    // users page
    cy.urlmatch(/\/admin\/users$/)

    cy.get('.alert-success', { timeout: 10000 }).should(
      'contain',
      'Votre invitation a bien été envoyée ! Vous pouvez à présent éditer les paramètres du nouvel utilisateur.',
    )
  })
})
