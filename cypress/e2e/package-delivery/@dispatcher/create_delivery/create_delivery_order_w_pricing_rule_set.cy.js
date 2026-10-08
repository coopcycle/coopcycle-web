context('Delivery (role: dispatcher)', () => {
  beforeEach(() => {
    cy.loadFixtures([
      'setup_default.yml',
      'user_dispatcher.yml',
      'store_basic.yml',
      'pricing_rule_set_flat.yml',
    ])

    cy.setMockDateTime('2025-04-23 8:30:00')

    cy.login('dispatcher', 'dispatcher')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  it('create delivery order with another pricing rule set', function () {
    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une nouvelle commande')
      .click()

    // Create delivery page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries\/new$/)

    cy.betaEnterAddressAtPosition(
      0,
      '23 Avenue Claude Vellefaux, 75010 Paris, France',
      /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
      'Warehouse',
      '+33112121212',
      'John Doe',
    )

    cy.betaEnterAddressAtPosition(
      1,
      '72 Rue Saint-Maur, 75011 Paris, France',
      /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
      'Office',
      '+33112121414',
      'Jane smith',
    )

    // Priced with the store's rule set by default
    cy.get('.ant-select[data-testid="pricing-rule-set-select"]')
      .should('have.class', 'ant-select-disabled')
      .contains('Default (par défaut du magasin)')
    cy.get('[data-testid="tax-included"]').contains('4,99 €')

    cy.get('[data-testid="pricing-rule-set-checkbox"]').check()
    cy.get('.ant-select[data-testid="pricing-rule-set-select"]').should(
      'not.have.class',
      'ant-select-disabled',
    )

    // antdSelect looks for the dropdown from the current root, which has to be within the page body
    cy.get('[data-testid="price-adjustments"]').within(() => {
      cy.antdSelect('.ant-select[data-testid="pricing-rule-set-select"]', 'Flat')
    })

    cy.get('[data-testid="tax-included"]').contains('15,00 €')

    cy.get('button[type="submit"]').click()

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    cy.get('[data-testid="order-total-including-tax"]')
      .find('[data-testid="value"]')
      .contains('€15.00')

    cy.get('[data-testid="order-edit"]').click()

    // Edit Delivery page: the chosen rule set is kept
    cy.urlmatch(/\/admin\/deliveries\/[0-9]+$/)

    cy.get('[data-testid="pricing-rule-set-checkbox"]').should('be.checked')
    cy.get('.ant-select[data-testid="pricing-rule-set-select"]').contains(
      'Flat',
    )
  })
})
