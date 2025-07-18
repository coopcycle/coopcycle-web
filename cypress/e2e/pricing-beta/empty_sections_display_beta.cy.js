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
          'Ajouter une règle à appliquer pour chaque point (tâche de RETRAIT et/ou de DÉPÔT), recommandée pour les livraisons complexes à points multiples',
        )
      })

      cy.get('[data-testid="pricing-rule-set-target-delivery"]').within(() => {
        cy.get('.ant-alert-info').should(
          'contain',
          'Ajouter une règle à appliquer une fois par commande, recommandée pour les livraisons simples (un retrait, un dépôt) et les commandes Food Tech',
        )
      })
    })
  },
)
