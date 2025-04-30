context('Delivery (role: admin)', () => {
    beforeEach(() => {
      cy.symfonyConsole(
        'coopcycle:fixtures:load ' +
        '-s cypress/fixtures/setup_default.yml ' +
        '-f cypress/fixtures/admin_user.yml ' +
        '-f features/fixtures/ORM/store_w_distance_pricing.yml',
      )

      cy.setMockDateTime('2025-04-23 8:30:00')

      cy.visit('/login')
      cy.login('admin', '12345678')
    })

    afterEach(() => {
      cy.resetMockDateTime()
    })

    it('[beta form] create delivery order', function () {
      cy.visit('/admin/stores')

      cy.get('[data-testid=store_Acme__list_item]')
        .find('.dropdown-toggle')
        .click()

      cy.get('[data-testid=store_Acme__list_item]')
        .contains('Créer une livraison')
        .click()

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

      cy.get('[data-testid="tax-included"]').contains('1,99 €')

      cy.get('button[type="submit"]').click()

      // list of deliveries page
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
        .contains(/€1.99/)
        .should('exist')

      cy.get('[data-testid="delivery__list_item"]')
        .find('[data-testid="delivery_id"]')
        .click()

      // Delivery page
      //TODO: verify that all input data is saved correctly
      cy.get('[data-testid="breadcrumb"]')
        .find('[data-testid="order_id"]')
        .should('exist')

      cy.get('[data-testid="breadcrumb"]')
        .find('[data-testid="order_id"]')
        .click()

      // Order page
      cy.location('pathname', { timeout: 10000 }).should(
        'match',
        /\/admin\/orders\/[0-9]+$/,
      )

      cy.get('[data-testid="order_item"]')
        .find('[data-testid="total"]')
        .contains('€1.99')
    })

  })
