context('Delivery (role: dispatcher)', () => {
  beforeEach(() => {
    cy.loadFixtures([
      'setup_default.yml',
      'user_dispatcher.yml',
      'tags.yml',
      'store_with_manual_supplements.yml',
    ])
    cy.setEnvVar('PACKAGE_DELIVERY_UI_PRICE_BREAKDOWN_ENABLED', '1')

    cy.setMockDateTime('2025-04-23 8:30:00')

    cy.login('dispatcher', 'dispatcher')
  })

  afterEach(() => {
    cy.resetMockDateTime()
    cy.removeEnvVar('PACKAGE_DELIVERY_UI_PRICE_BREAKDOWN_ENABLED')
  })

  it('create delivery order', function () {
    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Store_with_Manual_Supplements__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Store_with_Manual_Supplements__list_item]')
      .contains('Créer une nouvelle commande')
      .click()

    // Create delivery page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries\/new$/)

    // Pickup

    cy.betaEnterAddressAtPosition(
      0,
      '23 Avenue Claude Vellefaux, 75010 Paris, France',
      /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
      'Warehouse',
      '+33112121212',
      'John Doe',
    )

    cy.betaEnterCommentAtPosition(0, 'Pickup comments')

    cy.get(`[data-testid="form-task-0"]`).within(() => {
      cy.get(`[data-testid=tags-select]`).click()
    })
    cy.reactSelect(0)

    // Dropoff

    cy.betaEnterAddressAtPosition(
      1,
      '72 Rue Saint-Maur, 75011 Paris, France',
      /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
      'Office',
      '+33112121414',
      'Jane smith',
    )
    cy.betaEnterCommentAtPosition(1, 'Dropoff comments')

    cy.get(`[data-testid="form-task-1"]`).within(() => {
      cy.get(`[data-testid=tags-select]`).click()
    })
    cy.reactSelect(2)

    cy.verifyCart([
      {
        name: 'Supplément de commande',
        total: '4,99 €',
        options: [
          {
            name: 'Plus de 0.00 km',
            price: '4,99 €',
          },
        ],
      },
    ])

    cy.get('[data-testid="tax-included"]').contains('4,99 €')

    cy.get('[data-testid="manual-supplement-Fragile Handling"]')
      .check()

    cy.verifyCart([
      {
        name: 'Supplément de commande',
        total: '4,99 €',
        options: [
          {
            name: 'Plus de 0.00 km',
            price: '4,99 €',
          },
          {
            name: 'Fragile Handling',
            price: '3,00 €',
          },
        ],
      },
    ])

    cy.get('[data-testid="tax-included"]').contains('7,99 €')

    cy.get('button[type="submit"]').click()

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    cy.verifyCart([
      {
        name: 'Supplément de commande',
        //TODO: fix different formats between DeliveryForm and an Order page
        // total: '4,99 €',
        total: '€7.99',
        adjustments: [
          {
            name: 'Plus de 0.00 km',
            // price: '4,99 €',
            price: '€4.99',
          },
          {
            name: 'Fragile Handling',
            price: '€3.00',
          },
        ],
      },
    ])

    cy.get('[data-testid="order-total-including-tax"]')
      .find('[data-testid="value"]')
      .contains('€7.99')

    // Wait for React components to load
    cy.get('[data-testid="delivery-itinerary"]', {
      timeout: 10000,
    }).should('be.visible')
    cy.get('[data-testid=delivery-itinerary]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery-itinerary]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')
  })
})
