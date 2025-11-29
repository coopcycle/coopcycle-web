context('Pricing rules: validation (role: admin) - Beta Version', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup(['user_admin.yml', 'packages.yml'])
    cy.login('admin', '12345678')
  })

  it('validates required fields: condition', function () {
    cy.visit('/admin/deliveries/pricing/beta/new')

    // Wait for React components to load
    cy.get('[data-testid="pricing-rule-set-form"]', { timeout: 10000 }).should(
      'be.visible',
    )

    cy.get('input[id*="name"]').type('Matrix Validation Test')

    // Add a rule without conditions
    cy.get('[data-testid="pricing-rule-set-add-rule-target-delivery"]').click()

    cy.get('[data-testid="pricing-rule-set-rule-0"]', { timeout: 5000 }).should(
      'be.visible',
    )

    // Try to save without adding conditions or price
    cy.get('button[type="submit"]').click()

    // Should show validation errors for missing expression and price
    cy.get('.ant-alert-message', { timeout: 5000 })
      .should('be.visible')
      .and('contain', 'Ajoutez au moins une condition')
      .and('contain', 'Ajoutez un prix')
  })
})
