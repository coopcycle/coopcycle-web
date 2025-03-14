context('Setup simple matrix pricing (role: admin)', () => {
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

  it('creates simple matrix pricing rule', function () {
    cy.visit('/admin/deliveries/pricing')

    // List of all pricing rule sets
    cy.get('[data-testid="pricing_rule_sets_add"]').click()

    // New pricing rule set page
    // Matrix:
    // columns: packages = SMALL, XL;
    // rows: diff_hours(pickup) > 4, < 4
    cy.get('#pricing_rule_set_name').type('Matrix')

    // Rule: packages = SMALL; diff_hours(pickup) > 4; price = 7
    cy.get('[data-testid="pricint_rule_set_add_rule_target_delivery"]').click();
    cy.get('[data-testid="rule-picker-add-condition"]').click();
    cy.get('tr > :nth-child(1) > .form-control').select('packages');
    cy.get('[width="25%"] > .form-control').select('SMALL');
    cy.get('[data-testid="rule-picker-add-condition"]').click();
    cy.get('tbody > :nth-child(2) > :nth-child(1) > .form-control').select('diff_hours(pickup)');
    cy.get(':nth-child(2) > [width="20%"] > .form-control').select('>');
    cy.get(':nth-child(2) > [width="25%"] > .form-control').type('4');
    cy.get('#pricing_rule_set_rules_0_price').type('7');

    // Rule: packages = SMALL; diff_hours(pickup) < 4; price = 10
    cy.get('[data-testid="pricint_rule_set_add_rule_target_delivery"]').click();
    cy.get(':nth-child(2) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .text-right > [data-testid="rule-picker-add-condition"]').click();
    cy.get(':nth-child(2) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > tr > :nth-child(1) > .form-control').select('packages');
    cy.get(':nth-child(2) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > tr > [width="25%"] > .form-control').select('SMALL');
    cy.get(':nth-child(2) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > div.text-right > [data-testid="rule-picker-add-condition"]').click();
    cy.get(':nth-child(2) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > :nth-child(2) > :nth-child(1) > .form-control').select('diff_hours(pickup)');
    cy.get(':nth-child(2) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > :nth-child(2) > [width="20%"] > .form-control').select('<');
    cy.get(':nth-child(2) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > :nth-child(2)').click();
    cy.get(':nth-child(2) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > :nth-child(2) > [width="25%"] > .form-control').clear();
    cy.get(':nth-child(2) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > :nth-child(2) > [width="25%"] > .form-control').type('4');
    cy.get('#pricing_rule_set_rules_1_price').type('10');

    // Rule: packages = XL; diff_hours(pickup) > 4; price = 12
    cy.get('[data-testid="pricint_rule_set_add_rule_target_delivery"]').click();
    cy.get(':nth-child(3) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .text-right > [data-testid="rule-picker-add-condition"]').click();
    cy.get(':nth-child(3) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > tr > :nth-child(1) > .form-control').select('packages');
    cy.get(':nth-child(3) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > tr > [width="25%"] > .form-control').select('XL');
    cy.get(':nth-child(3) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > div.text-right > [data-testid="rule-picker-add-condition"]').click();
    cy.get(':nth-child(3) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > :nth-child(2) > :nth-child(1) > .form-control').select('diff_hours(pickup)');
    cy.get(':nth-child(3) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > :nth-child(2) > [width="20%"] > .form-control').select('>');
    cy.get(':nth-child(3) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > :nth-child(2)').click();
    cy.get(':nth-child(3) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > :nth-child(2) > [width="25%"] > .form-control').clear();
    cy.get(':nth-child(3) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > :nth-child(2) > [width="25%"] > .form-control').type('4');
    cy.get('#pricing_rule_set_rules_2_price').type('12');

    // Rule: packages = XL; diff_hours(pickup) < 4; price = 15
    cy.get('[data-testid="pricint_rule_set_add_rule_target_delivery"]').click();
    cy.get(':nth-child(4) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .text-right > [data-testid="rule-picker-add-condition"]').click();
    cy.get(':nth-child(4) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > tr > :nth-child(1) > .form-control').select('packages');
    cy.get(':nth-child(4) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > tr > [width="25%"] > .form-control').select('XL');
    cy.get(':nth-child(4) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > div.text-right > [data-testid="rule-picker-add-condition"]').click();
    cy.get(':nth-child(4) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > :nth-child(2) > :nth-child(1) > .form-control').select('diff_hours(pickup)');
    cy.get(':nth-child(4) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > :nth-child(2) > [width="20%"] > .form-control').select('<');
    cy.get(':nth-child(4) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > :nth-child(2) > [width="25%"]').click();
    cy.get(':nth-child(4) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > :nth-child(2) > [width="25%"] > .form-control').clear();
    cy.get(':nth-child(4) > .delivery-pricing-ruleset__rule__main > .w-75 > .delivery-pricing-ruleset__rule__expression > .rule-expression-container > .rule-picker > .table > tbody > :nth-child(2) > [width="25%"] > .form-control').type('4');
    cy.get('#pricing_rule_set_rules_3_price').type('15');

    // Save button
    cy.get('.btn-block').click()

    cy.get('.alert-success').should('contain', 'Changements sauvegardés')
  })
})
