context('Modify delivery (role: store)', () => {
  afterEach(() => {
    cy.removeEnvVar('PACKAGE_DELIVERY_UI_PRICE_BREAKDOWN_ENABLED')
  })

  it('modify delivery until it is assigned, with price re-calculation', () => {
    // Price is 1.00 € per kg
    cy.loadFixturesWithSetup(['store_with_weight_pricing.yml'])
    cy.setEnvVar('PACKAGE_DELIVERY_UI_PRICE_BREAKDOWN_ENABLED', '0')

    cy.login('store_1', 'store_1')

    cy.urlmatch(/\/dashboard$/)

    cy.get('a').contains('Créer une nouvelle commande').click()

    cy.betaEnterAddressAtPosition(
      0,
      '23 Avenue Claude Vellefaux, 75010 Paris, France',
      /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
      'Office',
      '+33112121212',
      'John Doe',
    )

    cy.betaEnterAddressAtPosition(
      1,
      '72 Rue Saint-Maur, 75011 Paris, France',
      /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
      'Office',
      '+33112121212',
      'Jane smith',
    )

    cy.betaEnterWeightAtPosition(1, 2.5)

    cy.get('[data-testid="tax-included"]').contains('3,00 €')

    cy.get('button[type="submit"]').click()

    // Delivery page (edit mode)
    cy.urlmatch(/\/dashboard\/deliveries\/[0-9]+$/)

    cy.get('[data-testid="tax-included"]').contains('3,00 €')
    cy.get('[data-testid="delivery-assigned-alert"]').should('not.exist')

    // Modify the weight; the price is re-calculated
    cy.betaEnterWeightAtPosition(1, 5)

    cy.get('[data-testid="tax-included-previous"]').contains('3,00 €')
    cy.get('[data-testid="tax-included"]').contains('5,00 €')

    // Save the changes
    cy.get('button[type="submit"]').should('exist').click()

    cy.urlmatch(/\/dashboard\/deliveries\/[0-9]+$/)

    cy.get('[data-testid="tax-included"]').contains('5,00 €')
    cy.get('[data-testid="tax-included-previous"]').should('not.exist')
  })

  it('can not modify a delivery once it is assigned', () => {
    cy.loadFixturesWithSetup([
      'store_basic.yml',
      'package_delivery_order_assigned.yml',
    ])
    cy.setEnvVar('PACKAGE_DELIVERY_UI_PRICE_BREAKDOWN_ENABLED', '0')

    cy.login('store_1', 'store_1')

    cy.visit('/dashboard/deliveries/1')

    cy.get('[data-testid="delivery-itinerary"]', {
      timeout: 10000,
    }).should('be.visible')

    cy.get('[data-testid="delivery-assigned-alert"]').should('be.visible')
    cy.get('button[type="submit"]').should('not.exist')
    cy.get('[data-testid="cancel-delivery-button"]').should('not.exist')
  })

  it('cancel a delivery, with a confirmation', () => {
    cy.loadFixturesWithSetup([
      'store_basic.yml',
      'package_delivery_order_multi_dropoff.yml',
    ])
    cy.setEnvVar('PACKAGE_DELIVERY_UI_PRICE_BREAKDOWN_ENABLED', '0')

    cy.login('store_1', 'store_1')

    cy.visit('/dashboard/deliveries/1')

    cy.get('[data-testid="delivery-itinerary"]', {
      timeout: 10000,
    }).should('be.visible')

    cy.get('[data-testid="cancel-delivery-button"]').click()

    // Dismiss the confirmation: nothing happens
    cy.get('.ant-popconfirm').should('be.visible')
    cy.get('.ant-popconfirm .ant-btn-default').click()
    cy.get('.ant-popconfirm').should('not.be.visible')
    cy.urlmatch(/\/dashboard\/deliveries\/1$/)

    // Confirm the cancellation
    cy.get('[data-testid="cancel-delivery-button"]').click()
    cy.get('.ant-popconfirm .ant-btn-dangerous').click()

    cy.urlmatch(/\/dashboard$/)

    // The delivery is cancelled, it can not be cancelled again
    cy.visit('/dashboard/deliveries/1')
    cy.get('[data-testid="delivery-itinerary"]', {
      timeout: 10000,
    }).should('be.visible')
    cy.get('[data-testid="cancel-delivery-button"]').should('not.exist')
  })
})
