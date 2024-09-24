context('Delivery via form (payment: Stripe only)', () => {
  beforeEach(() => {
    const prefix = Cypress.env('COMMAND_PREFIX')

    let cmd =
      'bin/console coopcycle:fixtures:load -f cypress/fixtures/stores.yml --env test'
    if (prefix) {
      cmd = `${prefix} ${cmd}`
    }

    cy.exec(cmd)
  })

  it('should create a delivery', () => {
    cy.visit('/fr/embed/delivery/start')

    // Pickup

    cy.searchAddress(
      '[data-form="task"]:nth-of-type(1)',
      '91 rue de rivoli paris',
      /^91,? Rue de Rivoli,? 75001,? Paris,? France/i,
    )

    // Dropoff

    cy.searchAddress(
      '[data-form="task"]:nth-of-type(2)',
      '120 rue st maur paris',
      /^120,? Rue Saint-Maur,? 75011,? Paris,? France/i,
    )

    cy.get('[data-form="task"]').each($el => {
      cy.wrap($el)
        .find('[id$="address_newAddress_latitude"]')
        .invoke('val')
        .should('match', /[0-9.]+/)
      cy.wrap($el)
        .find('[id$="address_newAddress_longitude"]')
        .invoke('val')
        .should('match', /[0-9.]+/)
    })

    cy.get('#delivery_name').type('John Doe', { timeout: 5000, delay: 30 })
    cy.get('#delivery_email').type('dev@coopcycle.org', {
      timeout: 5000,
      delay: 30,
    })
    cy.get('#delivery_telephone').type('0612345678', {
      timeout: 5000,
      delay: 30,
    })

    cy.get('form[name="delivery"]').submit()

    cy.location('pathname').should(
      'match',
      /\/fr\/forms\/[a-zA-Z0-9]+\/summary/,
    )

    cy.get('.alert-info')
      .invoke('text')
      .should('match', /Vous avez demandé une course qui vous sera déposée le/)

    cy.get('form[name="checkout_payment"] input[type="text"]').type(
      'John Doe',
      { timeout: 5000, delay: 30 },
    )
    cy.enterCreditCard()

    cy.get('form[name="checkout_payment"]').submit()

    cy.location('pathname', { timeout: 30000 }).should(
      'match',
      /\/fr\/pub\/o\/[a-zA-Z0-9]+/,
    )
  })
})
