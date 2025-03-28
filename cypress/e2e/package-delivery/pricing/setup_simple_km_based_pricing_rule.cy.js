context('Setup simple km-based pricing (role: admin)', () => {
  beforeEach(() => {
    const prefix = Cypress.env('COMMAND_PREFIX')

    let cmd =
      'bin/console coopcycle:fixtures:load -s cypress/fixtures/setup.yml -f cypress/fixtures/pricing.yml --env test'
    if (prefix) {
      cmd = `${prefix} ${cmd}`
    }

    cy.exec(cmd)

    cy.visit('/login')
    cy.login('admin', '12345678')
  })

  it('creates simple km-based pricing rule', function () {
    cy.visit('/admin/deliveries/pricing')

    // List of all pricing rule sets
    cy.get('[data-testid="pricing_rule_sets_add"]').click()

    // New pricing rule set page
    cy.get('#pricing_rule_set_name').type('Old school')

    // Select strategy: All the matching rules
    cy.get('#pricing_rule_set_strategy > :nth-child(2) > .required').click()
    cy.get('#pricing_rule_set_strategy_1').check()

    // Rule: distance > 0; price = 5
    cy.get('[data-testid="pricing_rule_set_add_rule_target_delivery"]').click()
    cy.get('[data-testid="rule-picker-add-condition"]').click()
    cy.get('tr > :nth-child(1) > .form-control').select('distance')
    cy.get('[width="20%"] > .form-control').select('>')
    cy.get('#pricing_rule_set_rules_0_price').type('5')

    // Rule: distance > 3; price = 3 per 2km above 1km
    cy.get('[data-testid="pricing_rule_set_add_rule_target_delivery"]').click()
    cy.get(':nth-child(2) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .text-right > [data-testid="rule-picker-add-condition"]',).click()
    cy.get(':nth-child(2) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > tr > :nth-child(1) > .form-control',).select('distance')
    cy.get(':nth-child(2) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > tr > [width="20%"] > .form-control',).select('>')
    cy.get(':nth-child(2) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > tr > [width="25%"] > .form-control',).type('3')
    cy.get(':nth-child(2) > :nth-child(3) > .delivery-pricing-ruleset__rule__price > .d-flex > .delivery-pricing-ruleset__rule__price__label > [data-testid="pricing_rule_price_type_choice"]',).select('range')
    cy.get('.mr-2 > .form-control').type('3')
    cy.get('[data-testid="price_rule_price_range_editor"] > :nth-child(2) > input.form-control',).type('2')
    cy.get('[data-testid="price_rule_price_range_editor"] > :nth-child(3) > .form-control',).type('1')

    // Save button
    cy.get('.btn-block').click()

    cy.get('.alert-success').should('contain', 'Changements sauvegardés')
  })
})
