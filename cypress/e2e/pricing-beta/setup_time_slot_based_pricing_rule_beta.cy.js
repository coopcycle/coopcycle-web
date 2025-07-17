context(
  'Setup pricing based on time slots using React interface (role: admin) - Beta Version',
  () => {
    beforeEach(() => {
      cy.loadFixturesWithSetup([
        'ORM/user_admin.yml',
        'ORM/time_slots_pagination.yml',
      ])
      cy.login('admin', '12345678')
    })

    it('creates pricing based on time slots using React interface', function () {
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
      cy.get('input[id*="name"]').type('Using time slots Beta')

      // Select strategy: All the matching rules (map strategy)
      cy.get('input[value="map"]').check()

      // First time slot rule
      cy.get('[data-testid="pricing-rule-set-add-rule-target-task"]').click()

      // Wait for the rule to be added and form to be visible
      cy.get('[data-testid="pricing-rule-set-rule-0"]', { timeout: 5000 }).should(
        'be.visible',
      )

      cy.get('[data-testid="pricing-rule-set-rule-0"]').within(() => {
        cy.get('[data-testid="rule-add-condition"]').click()
        cy.get('[data-testid="condition-type-select"]').select('time_slot')
        cy.get('[data-testid="condition-operator-select"]').select('==')
        cy.antdSelect(
          '[data-testid="condition-time-slot-select"]',
          'Acme time slot 2',
        )

        // Set price
        cy.get('[data-testid="rule-fixed-price-input"]').clear()
        cy.get('[data-testid="rule-fixed-price-input"]').type('7')
      })

      // Second time slot rule
      cy.get('[data-testid="pricing-rule-set-add-rule-target-task"]').click()

      cy.get('[data-testid="pricing-rule-set-rule-1"]', { timeout: 5000 }).should(
        'be.visible',
      )

      cy.get('[data-testid="pricing-rule-set-rule-1"]').within(() => {
        cy.get('[data-testid="rule-add-condition"]').click()
        cy.get('[data-testid="condition-type-select"]').select('time_slot')
        cy.get('[data-testid="condition-operator-select"]').select('==')
        cy.antdSelect(
          '[data-testid="condition-time-slot-select"]',
          'Acme time slot 15',
        )

        // Set price
        cy.get('[data-testid="rule-fixed-price-input"]').clear()
        cy.get('[data-testid="rule-fixed-price-input"]').type('15')
      })

      // Save
      cy.intercept('POST', '/api/pricing_rule_sets').as('postPricingRuleSet')
      cy.get('button[type="submit"]').click()
      cy.wait('@postPricingRuleSet', { timeout: 10000 })

      // Should redirect to edit page
      cy.urlmatch(/\/admin\/deliveries\/pricing\/beta\/[0-9]+$/)

      //TODO: verify data
    })
  },
)
