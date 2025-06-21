context('Delivery (role: dispatcher)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup([
      'ORM/user_dispatcher.yml',
      'ORM/tags.yml',
      'ORM/store_advanced.yml',
    ])

    cy.setMockDateTime('2025-04-23 8:30:00')

    cy.login('dispatcher', 'dispatcher')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  it('create delivery order', function () {
    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une nouvelle commande')
      .click()

    // Create delivery page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries\/new$/)

    // Pickup

    cy.betaEnterAddressAtPosition(
      0,
      '23 Avenue Claude Vellefaux, 75010 Paris, France',
      /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
      'Warehouse',
      '+33112121212',
      'John Doe',
    )

    cy.betaEnterCommentAtPosition(0, 'Pickup comments')

    cy.get(`[data-testid="form-task-0"]`).within(() => {
      cy.get(`[data-testid=tags-select]`).click()
    })
    cy.get('#react-select-3-option-0').click()

    // Dropoff

    cy.betaEnterAddressAtPosition(
      1,
      '72 Rue Saint-Maur, 75011 Paris, France',
      /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
      'Office',
      '+33112121414',
      'Jane smith',
    )
    cy.betaEnterCommentAtPosition(1, 'Dropoff comments')

    cy.get(`[data-testid="form-task-1"]`).within(() => {
      cy.get(
        '[data-testid="/api/packages/1"] > .packages-item__quantity > :nth-child(3)',
      ).click()
    })
    cy.betaEnterWeightAtPosition(1, 2.5)

    cy.get(`[data-testid="form-task-1"]`).within(() => {
      cy.get(`[data-testid=tags-select]`).click()
    })
    cy.get('#react-select-5-option-2').click()

    cy.get('[data-testid="tax-included"]').contains('4,99 €')

    cy.get('button[type="submit"]').click()

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    cy.get('[data-testid="order_item"]')
      .find('[data-testid="total"]')
      .contains('€4.99')

    cy.get('[data-testid=delivery-itinerary]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery-itinerary]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')

    cy.get('[data-testid="order-edit"]').click()

    // Edit Delivery page
    cy.urlmatch(/\/admin\/deliveries\/[0-9]+$/)

    //verify that all the fields are saved correctly

    cy.betaTaskShouldHaveValue({
      taskFormIndex: 0,
      addressName: 'Warehouse',
      telephone: '01 12 12 12 12',
      contactName: 'John Doe',
      address: /23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/,
      date: '23 avril 2025',
      timeAfter: '00:00',
      timeBefore: '11:59',
      comments: 'Pickup comments',
      tags: ['Important'],
    })

    cy.betaTaskShouldHaveValue({
      taskFormIndex: 1,
      addressName: 'Office',
      telephone: '01 12 12 14 14',
      contactName: 'Jane smith',
      address: /72,? Rue Saint-Maur,? 75011,? Paris,? France/,
      date: '23 avril 2025',
      timeAfter: '00:00',
      timeBefore: '11:59',
      packages: [
        {
          nodeId: '/api/packages/1',
          quantity: 1,
        },
      ],
      weight: 2.5,
      comments: 'Dropoff comments',
      tags: ['Perishable'],
    })

    cy.get('[data-testid="tax-included-previous"]').contains('4,99 €')
  })
})
