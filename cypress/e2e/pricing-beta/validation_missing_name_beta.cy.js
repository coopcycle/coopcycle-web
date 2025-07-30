context('Pricing rules: validation (role: admin) - Beta Version', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup('ORM/user_admin.yml')
    cy.login('admin', '12345678')
  })

  it('validates required fields: name', function () {
    cy.visit('/admin/deliveries/pricing/beta/new')

    // Wait for React components to load
    cy.get('[data-testid="pricing-rule-set-form"]', { timeout: 10000 }).should(
      'be.visible',
    )

    // Try to save without filling required fields
    cy.get('button[type="submit"]').click()

    // Should show validation errors
    cy.get('.ant-form-item-explain-error', { timeout: 5000 })
      .should('be.visible')
      .and('contain', 'Requis')
  })
})
