context('Delivery (role: admin); store with time slot pricing', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup([
      'ORM/user_admin.yml',
      '../features/fixtures/ORM/store_w_time_slot_pricing.yml',
    ])

    cy.setMockDateTime('2025-04-23 8:30:00')

    cy.login('admin', '12345678')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  it('create delivery order with manually selected range NOT belonging to a time slot choices', function () {
    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une livraison')
      .click()

    // Create delivery page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries\/new$/)

    // Pickup

    cy.betaEnterAddressAtPosition(
      0,
      '23 Avenue Claude Vellefaux, 75010 Paris, France',
      /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
      'Office',
      '+33112121212',
      'John Doe',
    )

    //Set pickup time range to 12:30 - 13:30 manually
    cy.get('[data-testid="form-task-0"]').within(() => {
      cy.get('.timeslot-container > .timeslot-container__icon').click()
      cy.antdSelect('.ant-select[data-testid="select-after"]', '12:30')
      cy.antdSelect('.ant-select[data-testid="select-before"]', '13:30')
    })

    cy.betaEnterCommentAtPosition(0, 'Pickup comments')

    // Dropoff

    cy.betaEnterAddressAtPosition(
      1,
      '72 Rue Saint-Maur, 75011 Paris, France',
      /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
      'Office',
      '+33112121212',
      'Jane smith',
    )

    cy.betaEnterWeightAtPosition(1, 2.5)

    cy.betaEnterCommentAtPosition(1, 'Dropoff comments')

    cy.get('[data-testid="tax-included"]').contains('2,00 €')

    cy.get('button[type="submit"]').click()

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    cy.get('[data-testid="order_item"]')
      .find('[data-testid="total"]')
      .contains('€2.00')

    cy.get('[data-testid=delivery-itinerary]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery-itinerary]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')
  })
})
