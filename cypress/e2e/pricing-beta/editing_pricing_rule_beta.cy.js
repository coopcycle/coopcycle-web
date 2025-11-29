context(
  'Editing pricing rule using React interface (role: admin) - Beta Version',
  () => {
    beforeEach(() => {
      cy.loadFixturesWithSetup([
        'user_admin.yml',
        'tags.yml',
        'store_multi_dropoff.yml',
      ])
      cy.login('admin', '12345678')
    })

    it('edits pricing rule using React interface', function () {
      cy.visit('/admin/deliveries/pricing/beta/1')

      // Wait for React components to load
      cy.get('[data-testid="pricing-rule-set-form"]', {
        timeout: 10000,
      }).should('be.visible')

      cy.validatePricingRuleSet({
        name: 'Default',
        strategy: 'find',
        deliveryRules: [
          {
            index: 0,
            conditions: [
              {
                type: 'distance',
                operator: '>',
                value: '0',
              },
            ],
            price: {
              type: 'fixed',
              value: '4.99',
            },
          },
        ],
      })

      // Select strategy: All the matching rules (map strategy)
      cy.get('input[value="map"]').check()

      // Rule: 7.20 for each dropoff (TASK rule)
      cy.get('[data-testid="pricing-rule-set-add-rule-target-task"]').click()

      // Wait for the rule to be added and form to be visible
      cy.get('[data-testid="pricing-rule-set-rule-0"]', {
        timeout: 5000,
      }).should('be.visible')

      cy.get('[data-testid="pricing-rule-set-rule-0"]').within(() => {
        // Add condition for task type
        cy.get('[data-testid="rule-add-condition"]').click()
        cy.get('[data-testid="condition-type-select"]').select('task.type')
        cy.get('[data-testid="condition-task-type-select"]').select('DROPOFF')

        // Set price
        cy.get('[data-testid="rule-fixed-price-input"]').type('{selectall}7.20')
      })

      cy.get('[data-testid="pricing-rule-set-rule-1"]').within(() => {
        cy.get('[data-testid="rule-name"]').clear()
        cy.get('[data-testid="rule-name"]').type('Per order')
      })

      // Save
      cy.intercept('PUT', '/api/pricing_rule_sets/1').as('putPricingRuleSet')
      cy.get('button[type="submit"]').click()
      cy.wait('@putPricingRuleSet', { timeout: 10000 })

      // Should stay on the same page
      cy.urlmatch(/\/admin\/deliveries\/pricing\/beta\/[0-9]+$/)

      // Reload page to verify data is persisted
      cy.reload()

      // Wait for React components to load
      cy.get('[data-testid="pricing-rule-set-form"]', {
        timeout: 10000,
      }).should('be.visible')

      // Verify saved data
      cy.validatePricingRuleSet({
        name: 'Default',
        strategy: 'map',
        taskRules: [
          {
            index: 0,
            conditions: [
              {
                type: 'task.type',
                operator: '==',
                value: 'DROPOFF',
              },
            ],
            price: { type: 'fixed', value: '7.20' },
          },
        ],
        deliveryRules: [
          {
            index: 1,
            name: 'Per order',
            conditions: [
              {
                type: 'distance',
                operator: '>',
                value: '0',
              },
            ],
            price: {
              type: 'fixed',
              value: '4.99',
            },
          },
        ],
      })
    })
  },
)
