context(
  'Pricing rules: removal functionality (role: admin) - Beta Version',
  () => {
    beforeEach(() => {
      cy.loadFixturesWithSetup(['ORM/user_admin.yml', 'ORM/packages.yml'])
      cy.login('admin', '12345678')
    })

    it('tests rule removal functionality', function () {
      cy.visit('/admin/deliveries/pricing/beta/new')

      // Wait for React components to load
      cy.get('[data-testid="pricing-rule-set-form"]', {
        timeout: 10000,
      }).should('be.visible')

      cy.get('input[id*="name"]').type('Rule Removal Test')

      // Add multiple task rules
      for (let i = 0; i < 3; i++) {
        cy.get('[data-testid="pricing-rule-set-add-rule-target-task"]').click()

        cy.get(`[data-testid="pricing-rule-set-rule-${i}"]`, {
          timeout: 5000,
        }).should('be.visible')

        cy.get(`[data-testid="pricing-rule-set-rule-${i}"]`).within(() => {
          cy.get('[data-testid="rule-add-condition"]').click()
          cy.get('[data-testid="condition-type-select"]').select('task.type')
          cy.get('[data-testid="condition-task-type-select"]').select('DROPOFF')
          cy.get('[data-testid="rule-fixed-price-input"]').clear()
          cy.get('[data-testid="rule-fixed-price-input"]').type(
            `${(i + 1) * 5}`,
          )
        })
      }

      // Verify all rules are present
      cy.get('[data-testid="pricing-rule-set-target-task"]').within(() => {
        cy.get('[data-testid^="pricing-rule-set-rule-"]').should(
          'have.length',
          3,
        )
      })

      // Remove the middle rule
      cy.get('[data-testid="pricing-rule-set-rule-1"]').within(() => {
        cy.get('[data-testid="rule-remove"]').click()
      })

      // Verify rule was removed
      cy.get('[data-testid="pricing-rule-set-target-task"]').within(() => {
        cy.get('[data-testid^="pricing-rule-set-rule-"]').should(
          'have.length',
          2,
        )
      })
    })
  },
)
