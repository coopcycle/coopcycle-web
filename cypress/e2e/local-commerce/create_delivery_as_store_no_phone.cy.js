context('Delivery (role: store)', () => {
  beforeEach(() => {
    const prefix = Cypress.env('COMMAND_PREFIX')

    let cmd =
      'bin/console coopcycle:fixtures:load -f cypress/fixtures/stores.yml --env test'
    if (prefix) {
      cmd = `${prefix} ${cmd}`
    }

    cy.exec(cmd)
  })

  it('does not create delivery if no phone number', () => {
    cy.intercept('/api/routing/route/*').as('apiRoutingRoute')

    cy.visit('/login')

    cy.login('store_1', 'store_1')

    cy.location('pathname').should('eq', '/dashboard')

    cy.get('a').contains('Créer une livraison').click()

    // Pickup

    cy.searchAddress(
      '[data-form="task"]:nth-of-type(1)',
      '23 Avenue Claude Vellefaux, 75010 Paris, France',
      /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
    )

    cy.get('#delivery_tasks_0_address_name__display')
      .clear()
    cy.get('#delivery_tasks_0_address_name__display')
      .type('Office')

    cy.get('#delivery_tasks_0_address_contactName__display').clear()
    cy.get('#delivery_tasks_0_address_contactName__display').type('John Doe')
   
    
    cy.get('#delivery_tasks_0_comments').type('Pickup comments')

    cy.get('#delivery-submit').click()

    cy.get('#delivery_tasks_0_address_telephone').then(($input) => {
      expect($input[0].validationMessage).to.not.equal('')
    })
  })
})
