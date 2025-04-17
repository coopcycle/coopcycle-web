context('Setup simple multi-point pricing (role: admin)', () => {
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

  it('creates simple multi-point pricing rule', function () {
    cy.visit('/admin/deliveries/pricing')

    // List of all pricing rule sets
    cy.get('[data-testid="pricing_rule_sets_add"]').click()

    // New pricing rule set page
    cy.get('#pricing_rule_set_name').type('Multi-point pricing')

    // Select strategy: All the matching rules
    cy.get('#pricing_rule_set_strategy > :nth-child(2) > .required').click()
    cy.get('#pricing_rule_set_strategy_1').check()

    // Rule; 7.20 for each dropoff
    cy.get('[data-testid="pricing_rule_set_add_rule_target_task"]').click();
    cy.get('[data-testid="pricing-rule-0"]')
      .within(()=> {
        cy.get('[data-testid="rule-picker-add-condition"]').click();
        cy.get('[data-testid="condition-0"] > :nth-child(1) > .form-control').select('task.type');
        cy.get('[width="25%"] > .form-control').select('DROPOFF');
        cy.get('#pricing_rule_set_rules_0_price').type('7.20');
      })

    // Rule: 1.50 for each dropoff with a package of type XL
    cy.get('[data-testid="pricing_rule_set_add_rule_target_task"]').click();
    cy.get('[data-testid="pricing-rule-1"]')
      .within(()=> {
        cy.get('.rule-picker  > .text-right > [data-testid="rule-picker-add-condition"]').click();
        cy.get('.rule-picker  > .table > tbody > [data-testid="condition-0"] > :nth-child(1) > .form-control').select('task.type');
        cy.get('.rule-picker  > .table > tbody > [data-testid="condition-0"] > [width="25%"] > .form-control').select('DROPOFF');
        cy.get('.rule-picker  > div.text-right > [data-testid="rule-picker-add-condition"]').click();
        cy.get('[data-testid="condition-1"] > :nth-child(1) > .form-control').select('packages');
        cy.get('[data-testid="condition-1"] > [width="25%"] > .form-control').select('XL');
        cy.get('#pricing_rule_set_rules_1_price').type('1.50');
      })

    // Save button
    cy.get('.btn-block').click()

    cy.get('.alert-success').should('contain', 'Changements sauvegardés')
  })
})
