context(
  'Pricing rules: empty sections display (role: admin) - Beta Version',
  () => {
    beforeEach(() => {
      cy.loadFixturesWithSetup(['user_admin.yml', 'packages.yml'])
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
          'Ajouter des règles à appliquer pour chaque point (tâche de RETRAIT et/ou de DÉPÔT)',
        )
      })

      cy.get('[data-testid="pricing-rule-set-target-delivery"]').within(() => {
        cy.get('.ant-alert-info').should(
          'contain',
          'Ajouter des règles à appliquer une fois par commande',
        )
      })
    })
  },
)
