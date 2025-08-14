context('Delivery (role: dispatcher)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup([
      'user_dispatcher.yml',
      'tags.yml',
      'store_basic.yml',
      'delivery_without_an_order.yml',
    ])
    cy.login('dispatcher', 'dispatcher')
  })

  it('modify a delivery without an order', function () {
    cy.visit('/admin/deliveries/1/')

    // Edit Delivery page
    cy.urlmatch(/\/admin\/deliveries\/[0-9]+$/)

    //verify all fields BEFORE modifications

    cy.betaTaskShouldHaveValue({
      taskFormIndex: 0,
      addressName: 'Warehouse',
      telephone: '01 12 12 12 12',
      contactName: 'John Doe',
      address: /23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/,
    })

    cy.betaTaskShouldHaveValue({
      taskFormIndex: 1,
      addressName: 'Office',
      telephone: '01 12 12 14 14',
      contactName: 'Jane smith',
      address: /72,? Rue Saint-Maur,? 75011,? Paris,? France/,
    })

    cy.betaEnterCommentAtPosition(1, 'New comment on a Dropoff task')

    cy.get('button[type="submit"]').click()

    // Edit Delivery page
    cy.urlmatch(/\/admin\/deliveries\/[0-9]+$/)

    //verify all fields AFTER modifications

    cy.betaTaskShouldHaveValue({
      taskFormIndex: 0,
      addressName: 'Warehouse',
      telephone: '01 12 12 12 12',
      contactName: 'John Doe',
      address: /23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/,
    })

    cy.betaTaskShouldHaveValue({
      taskFormIndex: 1,
      addressName: 'Office',
      telephone: '01 12 12 14 14',
      contactName: 'Jane smith',
      address: /72,? Rue Saint-Maur,? 75011,? Paris,? France/,
      comments: 'New comment on a Dropoff task',
    })
  })
})
