context('Setup simple matrix pricing (role: admin)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup(['user_admin.yml', 'packages.yml'])
    cy.login('admin', '12345678')
  })

  it('creates simple matrix pricing rule', function () {
    cy.visit('/admin/deliveries/pricing')

    // List of all pricing rule sets
    cy.get('[data-testid="pricing_rule_sets_add"]').click()

    // New pricing rule set page
    cy.urlmatch(/\/admin\/deliveries\/pricing\/new$/)
    // Matrix:
    // columns: packages = SMALL, XL;
    // rows: diff_hours(pickup) > 4, < 4
    cy.get('#pricing_rule_set_name').type('Matrix')

    // Rule: packages = SMALL; diff_hours(pickup) > 4; price = 7
    cy.get('[data-testid="pricing_rule_set_add_rule_target_delivery"]').click();
    cy.get('[data-testid="pricing-rule-0"]')
      .within(()=> {
        cy.get('[data-testid="rule-picker-add-condition"]').click();
        cy.get('tr > :nth-child(1) > .form-control').select('packages');
        cy.get('[width="25%"] > .form-control').select('SMALL');
        cy.get('[data-testid="rule-picker-add-condition"]').click();
        cy.get('tbody > :nth-child(2) > :nth-child(1) > .form-control').select('diff_hours(pickup)');
        cy.get(':nth-child(2) > [width="20%"] > .form-control').select('>');
        cy.get(':nth-child(2) > [width="25%"] > .form-control').type('4');
        cy.get('#pricing_rule_set_rules_0_price').type('7');
      })

    // Rule: packages = SMALL; diff_hours(pickup) < 4; price = 10
    cy.get('[data-testid="pricing_rule_set_add_rule_target_delivery"]').click();
    cy.get('[data-testid="pricing-rule-1"]')
      .within(()=> {
        cy.get('.rule-picker > .text-right > [data-testid="rule-picker-add-condition"]').click();
        cy.get('.rule-picker > .table > tbody > tr > :nth-child(1) > .form-control').select('packages');
        cy.get('.rule-picker > .table > tbody > tr > [width="25%"] > .form-control').select('SMALL');
        cy.get('.rule-picker > div.text-right > [data-testid="rule-picker-add-condition"]').click();
        cy.get('.rule-picker > .table > tbody > :nth-child(2) > :nth-child(1) > .form-control').select('diff_hours(pickup)');
        cy.get('.rule-picker > .table > tbody > :nth-child(2) > [width="20%"] > .form-control').select('<');
        cy.get('.rule-picker > .table > tbody > :nth-child(2)').click();
        cy.get('.rule-picker > .table > tbody > :nth-child(2) > [width="25%"] > .form-control').clear();
        cy.get('.rule-picker > .table > tbody > :nth-child(2) > [width="25%"] > .form-control').type('4');
        cy.get('#pricing_rule_set_rules_1_price').type('10');
      })

    // Rule: packages = XL; diff_hours(pickup) > 4; price = 12
    cy.get('[data-testid="pricing_rule_set_add_rule_target_delivery"]').click();
    cy.get('[data-testid="pricing-rule-2"]')
      .within(()=> {
        cy.get('.rule-picker > .text-right > [data-testid="rule-picker-add-condition"]').click();
        cy.get('.rule-picker > .table > tbody > tr > :nth-child(1) > .form-control').select('packages');
        cy.get('.rule-picker > .table > tbody > tr > [width="25%"] > .form-control').select('XL');
        cy.get('.rule-picker > div.text-right > [data-testid="rule-picker-add-condition"]').click();
        cy.get('.rule-picker > .table > tbody > :nth-child(2) > :nth-child(1) > .form-control').select('diff_hours(pickup)');
        cy.get('.rule-picker > .table > tbody > :nth-child(2) > [width="20%"] > .form-control').select('>');
        cy.get('.rule-picker > .table > tbody > :nth-child(2)').click();
        cy.get('.rule-picker > .table > tbody > :nth-child(2) > [width="25%"] > .form-control').clear();
        cy.get('.rule-picker > .table > tbody > :nth-child(2) > [width="25%"] > .form-control').type('4');
        cy.get('#pricing_rule_set_rules_2_price').type('12');
      })

    // Rule: packages = XL; diff_hours(pickup) < 4; price = 15
    cy.get('[data-testid="pricing_rule_set_add_rule_target_delivery"]').click();
    cy.get('[data-testid="pricing-rule-3"]')
      .within(()=> {
        cy.get('.rule-picker > .text-right > [data-testid="rule-picker-add-condition"]').click();
        cy.get('.rule-picker > .table > tbody > tr > :nth-child(1) > .form-control').select('packages');
        cy.get('.rule-picker > .table > tbody > tr > [width="25%"] > .form-control').select('XL');
        cy.get('.rule-picker > div.text-right > [data-testid="rule-picker-add-condition"]').click();
        cy.get('.rule-picker > .table > tbody > :nth-child(2) > :nth-child(1) > .form-control').select('diff_hours(pickup)');
        cy.get('.rule-picker > .table > tbody > :nth-child(2) > [width="20%"] > .form-control').select('<');
        cy.get('.rule-picker > .table > tbody > :nth-child(2) > [width="25%"]').click();
        cy.get('.rule-picker > .table > tbody > :nth-child(2) > [width="25%"] > .form-control').clear();
        cy.get('.rule-picker > .table > tbody > :nth-child(2) > [width="25%"] > .form-control').type('4');
        cy.get('#pricing_rule_set_rules_3_price').type('15');
      })

    // Save button
    cy.get('.btn-block').click()

    // Pricing rule page
    cy.urlmatch(/\/admin\/deliveries\/pricing\/[0-9]+$/)

    cy.get('.alert-success', { timeout: 10000 })
      .should('contain', 'Changements sauvegardés')
  })
})
