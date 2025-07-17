context(
  'Setup simple multi-point pricing rule using React interface (role: admin) - Beta Version',
  () => {
    beforeEach(() => {
      cy.loadFixturesWithSetup(['ORM/user_admin.yml', 'ORM/packages.yml'])
      cy.login('admin', '12345678')
    })

    it('creates simple multi-point pricing rule using React interface', function () {
      cy.visit('/admin/deliveries/pricing')

      // List of all pricing rule sets
      cy.urlmatch(/\/admin\/deliveries\/pricing$/)
      cy.get('[data-testid="pricing_rule_sets_add"]').click()

      // New pricing rule set page
      cy.urlmatch(/\/admin\/deliveries\/pricing\/new$/)
      // Switch to beta version
      cy.get('a[href*="pricing/beta/new"]').click()

      // New pricing rule set page - Beta version
      cy.urlmatch(/\/admin\/deliveries\/pricing\/beta\/new$/)

      // Wait for React components to load
      cy.get('[data-testid="pricing-rule-set-form"]', {
        timeout: 10000,
      }).should('be.visible')

      // Fill in the name field
      cy.get('input[id*="name"]').type('Multi-point pricing Beta')

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

      // Rule: 1.50 for each dropoff with a package of type XL (TASK rule)
      cy.get('[data-testid="pricing-rule-set-add-rule-target-task"]').click()

      cy.get('[data-testid="pricing-rule-set-rule-1"]', {
        timeout: 5000,
      }).should('be.visible')

      cy.get('[data-testid="pricing-rule-set-rule-1"]').within(() => {
        // Add first condition: task type = DROPOFF
        cy.get('[data-testid="rule-add-condition"]').click()
        cy.get('[data-testid="condition-type-select"]').select('task.type')
        cy.get('[data-testid="condition-task-type-select"]').select('DROPOFF')

        // Add second condition: packages = XL
        cy.get('[data-testid="rule-add-condition"]').click()
        cy.get('[data-testid="condition-type-select"]')
          .last()
          .select('packages')
        cy.antdSelect('[data-testid="condition-package-select"]', 'XL')

        // Set price
        cy.get('[data-testid="rule-fixed-price-input"]').type('{selectall}1.50')
      })

      // Save
      cy.intercept('POST', '/api/pricing_rule_sets').as('postPricingRuleSet')
      cy.get('button[type="submit"]').click()
      cy.wait('@postPricingRuleSet', { timeout: 10000 })

      // Should redirect to edit page
      cy.urlmatch(/\/admin\/deliveries\/pricing\/beta\/[0-9]+$/)

      // Verify saved data
      cy.validatePricingRuleSet({
        name: 'Multi-point pricing Beta',
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
          {
            index: 1,
            conditions: [
              {
                type: 'task.type',
                operator: '==',
                value: 'DROPOFF',
              },
              {
                type: 'packages',
                operator: 'containsAtLeastOne',
                packageName: 'XL',
              },
            ],
            price: { type: 'fixed', value: '1.50' },
          },
        ],
      })
    })
  },
)
