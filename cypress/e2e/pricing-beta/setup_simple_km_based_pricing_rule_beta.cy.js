context(
  'Setup simple km-based pricing rule using React interface (role: admin) - Beta Version',
  () => {
    beforeEach(() => {
      cy.loadFixturesWithSetup('ORM/user_admin.yml')
      cy.login('admin', '12345678')
    })

    it('creates simple km-based pricing rule using React interface', function () {
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
      cy.get('input[id*="name"]').type('Old school Beta')

      // Select strategy: All the matching rules (map strategy)
      cy.get('input[value="map"]').check()

      // Add first delivery rule: distance > 0; price = 5
      cy.get(
        '[data-testid="pricing-rule-set-add-rule-target-delivery"]',
      ).click()

      // Wait for the rule to be added and form to be visible
      cy.get('[data-testid="pricing-rule-set-rule-0"]', {
        timeout: 5000,
      }).should('be.visible')

      cy.get('[data-testid="pricing-rule-set-rule-0"]').within(() => {
        cy.get('[data-testid="rule-add-condition"]').click()
        cy.get('[data-testid="condition-type-select"]').select('distance')
        cy.get('[data-testid="condition-operator-select"]').select('>')
        cy.get('[data-testid="condition-number-input"]').type('{selectall}0')

        // Set price
        cy.get('[data-testid="rule-fixed-price-input"]').type('{selectall}5')
      })

      // Add second delivery rule: distance > 3; price = 3 per 2km above 1km
      cy.get(
        '[data-testid="pricing-rule-set-add-rule-target-delivery"]',
      ).click()

      cy.get('[data-testid="pricing-rule-set-rule-1"]', {
        timeout: 5000,
      }).should('be.visible')

      cy.get('[data-testid="pricing-rule-set-rule-1"]').within(() => {
        cy.get('[data-testid="rule-name"]').clear()
        cy.get('[data-testid="rule-name"]').type('€3 per 2km')
        cy.get('[data-testid="rule-add-condition"]').click()
        cy.get('[data-testid="condition-type-select"]').select('distance')
        cy.get('[data-testid="condition-operator-select"]').select('>')
        cy.get('[data-testid="condition-number-input"]').type('{selectall}3')

        cy.get('[data-testid="rule-price-type"]').click()
        cy.root({ log: false })
          .closest('body')
          .within(() => {
            cy.get('[title="Prix TTC par tranches"]').click()
          })
        cy.get('[data-testid="rule-price-range-price"]').clear()
        cy.get('[data-testid="rule-price-range-price"]').type('3')
        cy.get('[data-testid="rule-price-range-step"]').clear()
        cy.get('[data-testid="rule-price-range-step"]').type('2')
        cy.get('[data-testid="rule-price-range-threshold"]').clear()
        cy.get('[data-testid="rule-price-range-threshold"]').type('1')
      })

      // Add manual supplement: "Return documents" with fixed price 5 eur
      cy.get(
        '[data-testid="pricing-rule-set-add-supplement-target-delivery"]',
      ).click()

      cy.get('[data-testid="pricing-rule-set-rule-2"]', {
        timeout: 5000,
      }).should('be.visible')

      cy.get('[data-testid="pricing-rule-set-rule-2"]').within(() => {
        cy.get('[data-testid="rule-name"]').type('Return documents')
        cy.get('[data-testid="rule-fixed-price-input"]').type('{selectall}5')
      })

      // Save
      cy.intercept('POST', '/api/pricing_rule_sets').as('postPricingRuleSet')
      cy.get('button[type="submit"]').click()
      cy.wait('@postPricingRuleSet', { timeout: 10000 })

      // Should redirect to edit page
      cy.urlmatch(/\/admin\/deliveries\/pricing\/beta\/[0-9]+$/)

      // Verify saved data
      cy.validatePricingRuleSet({
        name: 'Old school Beta',
        strategy: 'map',
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
              value: '5.00',
            },
          },
          {
            index: 1,
            name: '€3 per 2km',
            conditions: [
              {
                type: 'distance',
                operator: '>',
                value: '3',
              },
            ],
            price: {
              type: 'range',
              range: {
                price: '3',
                step: '2',
                threshold: '1',
              },
            },
          },
        ],
        deliveryManualSupplements: [
          {
            index: 2,
            name: 'Return documents',
            price: {
              type: 'fixed',
              value: '5.00',
            },
          },
        ],
      })
    })
  },
)
