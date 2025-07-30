context(
  'Setup simple matrix pricing rule using React interface (role: admin) - Beta Version',
  () => {
    beforeEach(() => {
      cy.loadFixturesWithSetup(['ORM/user_admin.yml', 'ORM/packages.yml'])
      cy.login('admin', '12345678')
    })

    it('creates simple matrix pricing rule using React interface', function () {
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
      cy.get('input[id*="name"]').type('Matrix Beta')

      // Select strategy: All the matching rules (map strategy)
      cy.get('input[value="map"]').check()

      // Matrix:
      // columns: packages = SMALL, XL;
      // rows: diff_hours(pickup) > 4, < 4

      // Rule: packages = SMALL; diff_hours(pickup) > 4; price = 7
      cy.get(
        '[data-testid="pricing-rule-set-add-rule-target-delivery"]',
      ).click()

      // Wait for the rule to be added and form to be visible
      cy.get('[data-testid="pricing-rule-set-rule-0"]', {
        timeout: 5000,
      }).should('be.visible')

      cy.get('[data-testid="pricing-rule-set-rule-0"]').within(() => {
        // Add first condition: packages = SMALL
        cy.get('[data-testid="rule-add-condition"]').click()
        cy.get('[data-testid="condition-type-select"]').select('packages')
        cy.antdSelect('[data-testid="condition-package-select"]', 'SMALL')

        // Add second condition: diff_hours(pickup) > 4
        cy.get('[data-testid="rule-add-condition"]').click()
        cy.get('[data-testid="condition-type-select"]')
          .last()
          .select('diff_hours(pickup)')
        cy.get('[data-testid="condition-operator-select"]').last().select('>')
        cy.get('[data-testid="condition-number-input"]')
          .last()
          .type('{selectall}4')

        // Set price
        cy.get('[data-testid="rule-fixed-price-input"]').type('{selectall}7')
      })

      // Rule: packages = SMALL; diff_hours(pickup) < 4; price = 10
      cy.get(
        '[data-testid="pricing-rule-set-add-rule-target-delivery"]',
      ).click()

      cy.get('[data-testid="pricing-rule-set-rule-1"]', {
        timeout: 5000,
      }).should('be.visible')

      cy.get('[data-testid="pricing-rule-set-rule-1"]').within(() => {
        // Add first condition: packages = SMALL
        cy.get('[data-testid="rule-add-condition"]').click()
        cy.get('[data-testid="condition-type-select"]').select('packages')
        cy.antdSelect('[data-testid="condition-package-select"]', 'SMALL')

        // Add second condition: diff_hours(pickup) < 4
        cy.get('[data-testid="rule-add-condition"]').click()
        cy.get('[data-testid="condition-type-select"]')
          .last()
          .select('diff_hours(pickup)')
        cy.get('[data-testid="condition-operator-select"]').last().select('<')
        cy.get('[data-testid="condition-number-input"]')
          .last()
          .type('{selectall}4')

        // Set price
        cy.get('[data-testid="rule-fixed-price-input"]').type('{selectall}10')
      })

      // Rule: packages = XL; diff_hours(pickup) > 4; price = 12
      cy.get(
        '[data-testid="pricing-rule-set-add-rule-target-delivery"]',
      ).click()

      cy.get('[data-testid="pricing-rule-set-rule-2"]', {
        timeout: 5000,
      }).should('be.visible')

      cy.get('[data-testid="pricing-rule-set-rule-2"]').within(() => {
        // Add first condition: packages = XL
        cy.get('[data-testid="rule-add-condition"]').click()
        cy.get('[data-testid="condition-type-select"]').select('packages')
        cy.antdSelect('[data-testid="condition-package-select"]', 'XL')

        // Add second condition: diff_hours(pickup) > 4
        cy.get('[data-testid="rule-add-condition"]').click()
        cy.get('[data-testid="condition-type-select"]')
          .last()
          .select('diff_hours(pickup)')
        cy.get('[data-testid="condition-operator-select"]').last().select('>')
        cy.get('[data-testid="condition-number-input"]')
          .last()
          .type('{selectall}4')

        // Set price
        cy.get('[data-testid="rule-fixed-price-input"]').type('{selectall}12')
      })

      // Rule: packages = XL; diff_hours(pickup) < 4; price = 15
      cy.get(
        '[data-testid="pricing-rule-set-add-rule-target-delivery"]',
      ).click()

      cy.get('[data-testid="pricing-rule-set-rule-3"]', {
        timeout: 5000,
      }).should('be.visible')

      cy.get('[data-testid="pricing-rule-set-rule-3"]').within(() => {
        // Add first condition: packages = XL
        cy.get('[data-testid="rule-add-condition"]').click()
        cy.get('[data-testid="condition-type-select"]').select('packages')
        cy.antdSelect('[data-testid="condition-package-select"]', 'XL')

        // Add second condition: diff_hours(pickup) < 4
        cy.get('[data-testid="rule-add-condition"]').click()
        cy.get('[data-testid="condition-type-select"]')
          .last()
          .select('diff_hours(pickup)')
        cy.get('[data-testid="condition-operator-select"]').last().select('<')
        cy.get('[data-testid="condition-number-input"]')
          .last()
          .type('{selectall}4')

        // Set price
        cy.get('[data-testid="rule-fixed-price-input"]').type('{selectall}15')
      })

      // Save
      cy.intercept('POST', '/api/pricing_rule_sets').as('postPricingRuleSet')
      cy.get('button[type="submit"]').click()
      cy.wait('@postPricingRuleSet', { timeout: 10000 })

      // Should redirect to edit page
      cy.urlmatch(/\/admin\/deliveries\/pricing\/beta\/[0-9]+$/)

      // Verify saved data
      cy.validatePricingRuleSet({
        name: 'Matrix Beta',
        strategy: 'map',
        deliveryRules: [
          {
            index: 0,
            conditions: [
              {
                type: 'packages',
                operator: 'containsAtLeastOne',
                packageName: 'SMALL',
              },
              { type: 'diff_hours(pickup)', operator: '>', value: '4' },
            ],
            price: { type: 'fixed', value: '7.00' },
          },
          {
            index: 1,
            conditions: [
              {
                type: 'packages',
                operator: 'containsAtLeastOne',
                packageName: 'SMALL',
              },
              { type: 'diff_hours(pickup)', operator: '<', value: '4' },
            ],
            price: { type: 'fixed', value: '10.00' },
          },
          {
            index: 2,
            conditions: [
              {
                type: 'packages',
                operator: 'containsAtLeastOne',
                packageName: 'XL',
              },
              { type: 'diff_hours(pickup)', operator: '>', value: '4' },
            ],
            price: { type: 'fixed', value: '12.00' },
          },
          {
            index: 3,
            conditions: [
              {
                type: 'packages',
                operator: 'containsAtLeastOne',
                packageName: 'XL',
              },
              { type: 'diff_hours(pickup)', operator: '<', value: '4' },
            ],
            price: { type: 'fixed', value: '15.00' },
          },
        ],
      })
    })
  },
)
