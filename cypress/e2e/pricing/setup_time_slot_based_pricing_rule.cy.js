context('Setup pricing based on time slots (role: admin)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup([
      'ORM/user_admin.yml',
      'ORM/time_slots_pagination.yml',
    ])
    cy.login('admin', '12345678')
  })

  it('creates pricing based on time slots', function () {
    cy.visit('/admin/deliveries/pricing')

    // List of all pricing rule sets
    cy.get('[data-testid="pricing_rule_sets_add"]').click()

    // New pricing rule set page
    cy.urlmatch(/\/admin\/deliveries\/pricing\/new$/)

    cy.get('#pricing_rule_set_name').type('Using time slots')

    cy.get('[data-testid="pricing_rule_set_add_rule_target_task"]').click()
    cy.get('[data-testid="pricing-rule-0"]').within(() => {
      cy.get('[data-testid="rule-picker-add-condition"]').click()
      cy.get(
        '[data-testid="condition-0"] > :nth-child(1) > .form-control',
      ).select('time_slot')
      cy.get('[width="20%"] > .form-control').select('==')
      cy.get('[width="25%"] > .form-control').select('/api/time_slots/20')
      cy.get('#pricing_rule_set_rules_0_price').type('7')
    })

    cy.get('[data-testid="pricing_rule_set_add_rule_target_task"]').click()
    cy.get('[data-testid="pricing-rule-1"]').within(() => {
      cy.get('[data-testid="rule-picker-add-condition"]').click()
      cy.get(
        '[data-testid="condition-0"] > :nth-child(1) > .form-control',
      ).select('time_slot')
      cy.get('[width="20%"] > .form-control').select('==')
      cy.get('[width="25%"] > .form-control').select('/api/time_slots/75')
      cy.get('#pricing_rule_set_rules_1_price').type('15')
    })

    // Save button
    cy.get('.btn-block').click()

    // Pricing rule page
    cy.urlmatch(/\/admin\/deliveries\/pricing\/[0-9]+$/)

    cy.get('.alert-success', { timeout: 10000 }).should(
      'contain',
      'Changements sauvegardés',
    )
  })
})
