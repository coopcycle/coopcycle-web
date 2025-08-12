context(
  'Pricing rules: empty sections display (role: admin) - Beta Version',
  () => {
    beforeEach(() => {
      cy.loadFixturesWithSetup(['ORM/user_admin.yml', 'ORM/packages.yml'])
      cy.login('admin', '12345678')
    })

    it('tests empty sections display', function () {
      cy.visit('/admin/deliveries/pricing/beta/new')

      // Wait for React components to load
      cy.get('[data-testid="pricing-rule-set-form"]', {
        timeout: 10000,
      }).should('be.visible')

      cy.get('[data-testid="pricing-rule-set-target-task"]').should(
        'be.visible',
      )
      cy.get('[data-testid="pricing-rule-set-target-delivery"]').should(
        'be.visible',
      )

      cy.get('[data-testid="pricing-rule-set-target-task"]').within(() => {
        cy.get('.ant-alert-info').should(
          'contain',
          'Add rules to be applied per each point (PICKUP and/or DROPOFF task)',
        )
      })

      cy.get('[data-testid="pricing-rule-set-target-delivery"]').within(() => {
        cy.get('.ant-alert-info').should(
          'contain',
          'Add rules to be applied once per order',
        )
      })
    })
  },
)
