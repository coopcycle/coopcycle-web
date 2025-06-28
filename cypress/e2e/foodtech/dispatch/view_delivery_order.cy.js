describe('Foodtech (role: dispatcher)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup([
      'ORM/user_dispatcher.yml',
      'ORM/foodtech_order_delivery.yml',
    ])
  })

  it('should view delivery order', () => {
    cy.login('dispatcher', 'dispatcher')

    cy.visit('/admin/orders/1')

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    //TODO: add assertions
  })
})
