context('Delivery (role: admin)', () => {
    beforeEach(() => {
      cy.symfonyConsole('coopcycle:fixtures:load -f cypress/fixtures/stores.yml')
      cy.setMockDateTime('2025-04-23 8:30:00')

      cy.visit('/login')
      cy.login('admin', '12345678')
    })

    afterEach(() => {
      cy.resetMockDateTime()
    })

    it('[beta form] create delivery for store with invalid pricing', function () {
      cy.visit('/admin/stores')
  
      cy.get('[data-testid=store_Acme_with_invalid_pricing__list_item]')
        .find('.dropdown-toggle')
        .click()
  
      cy.get('[data-testid=store_Acme_with_invalid_pricing__list_item]')
        .contains('Créer une livraison')
        .click()
  
      cy.wait(500)
  
      cy.get('body > div.content > div > div > div > a')
        .contains('click here')
        .click()
  
      // Pickup
  
      cy.betaEnterAddressAtPosition(
        0,
        '23 Avenue Claude Vellefaux, 75010 Paris, France',
        /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
        'Office',
        '+33112121212',
        'John Doe',
        'Pickup comments'
      )
  
      // Dropoff
  
      cy.betaEnterAddressAtPosition(
        1,
        '72 Rue Saint-Maur, 75011 Paris, France',
        /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
        'Office',
        '+33112121212',
        'Jane smith',
        'Dropoff comments'
      )
  
      cy.get(`[name="tasks[${1}].weight"]`).clear()
      cy.get(`[name="tasks[${1}].weight"]`).type(2.5)
  
      cy.get('[data-testid="tax-included"]').should('not.exist')
  
      cy.get('.alert-danger', { timeout: 10000 }).should(
        'contain',
        "Le prix n'a pas pu être calculé. Vous pouvez créer la livraison, n'oubliez pas de corriger la règle de prix liée à ce magasin.",
      )
  
      cy.get('button[type="submit"]').click()
  
      // TODO : check for proper redirect when implemented
      // cy.location('pathname', { timeout: 10000 }).should(
      //   'match',
      //   /\/admin\/stores\/[0-9]+\/deliveries$/,
      // )
  
      cy.location('pathname', { timeout: 10000 }).should(
        'match',
        /\/admin\/deliveries$/,
      )
  
      cy.get('[data-testid=delivery__list_item]')
        .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
        .should('exist')
      cy.get('[data-testid=delivery__list_item]')
        .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
        .should('exist')
      cy.get('[data-testid=delivery__list_item]')
        .contains(/€0.00/)
        .should('exist')
    })
  })
  