context('Delivery (role: dispatcher)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup([
      'ORM/user_dispatcher.yml',
      'ORM/tags.yml',
      '../features/fixtures/ORM/store_default.yml',
    ])

    cy.setMockDateTime('2025-04-23 8:30:00')

    cy.login('dispatcher', 'dispatcher')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  it('clone delivery order with arbitrary price', function () {
    cy.visit('/admin/stores')

    // List of stores
    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une nouvelle commande')
      .click()

    // Create delivery page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries\/new$/)

    // Pickup
    cy.betaChooseSavedAddressAtPosition(0, 1)

    cy.betaEnterCommentAtPosition(0, 'Pickup comments')

    cy.get(`[data-testid="form-task-0"]`).within(() => {
      cy.get(`[data-testid=tags-select]`).click()
    })
    cy.reactSelect(0)

    // Dropoff
    cy.betaChooseSavedAddressAtPosition(1, 2)

    cy.get(`[data-testid="form-task-1"]`).within(() => {
      cy.get(
        '[data-testid="/api/packages/1"] > .packages-item__quantity > :nth-child(3)',
      ).click()
    })
    cy.betaEnterWeightAtPosition(1, 2.5)

    cy.get(`[data-testid="form-task-1"]`).within(() => {
      cy.get(`[data-testid=tags-select]`).click()
    })
    cy.reactSelect(2)

    cy.betaEnterCommentAtPosition(1, 'Dropoff comments')

    cy.get('[name="delivery.override_price"]').check()
    cy.get('[name="variantName"]').type('Test product')
    cy.get('#variantPriceVAT').type('72')

    cy.get('button[type="submit"]').click()

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    // Advance to the next day to test that the time range is correct
    cy.setMockDateTime('2025-04-24 12:30:00')

    cy.get('[data-testid="order_clone"]').click()

    // Create delivery page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries\/new$/)

    //verify that all the fields are pre-loaded correctly

    cy.betaTaskShouldHaveValue({
      taskFormIndex: 0,
      addressName: 'Warehouse',
      telephone: '01 12 12 12 12',
      contactName: 'John Doe',
      address: /23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/,
      date: '24 avril 2025',
      hourRange: '12:00-23:59',
      comments: 'Pickup comments',
      tags: ['Important'],
    })

    cy.betaTaskShouldHaveValue({
      taskFormIndex: 1,
      addressName: 'Office',
      telephone: '01 12 12 14 14',
      contactName: 'Jane smith',
      address: /72,? Rue Saint-Maur,? 75011,? Paris,? France/,
      date: '24 avril 2025',
      hourRange: '12:00-23:59',
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

    cy.get('[name="delivery.override_price"]').should('be.checked')
    cy.get('[name="variantName"]').should('have.value', 'Test product')
    cy.get('#variantPriceVAT').should('have.value', '72')

    cy.get('button[type="submit"]').click()

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    cy.get('[data-testid="order-edit"]').click()

    // Edit Delivery page
    // hardcode the delivery id to make sure that we are on the right page (cloned order)
    cy.urlmatch(/\/admin\/deliveries\/2$/)

    //verify that all the fields are saved correctly

    cy.betaTaskShouldHaveValue({
      taskFormIndex: 0,
      addressName: 'Warehouse',
      telephone: '01 12 12 12 12',
      contactName: 'John Doe',
      address: /23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/,
      date: '24 avril 2025',
      timeAfter: '12:00',
      timeBefore: '23:59',
      comments: 'Pickup comments',
      tags: ['Important'],
    })

    cy.betaTaskShouldHaveValue({
      taskFormIndex: 1,
      addressName: 'Office',
      telephone: '01 12 12 14 14',
      contactName: 'Jane smith',
      address: /72,? Rue Saint-Maur,? 75011,? Paris,? France/,
      date: '24 avril 2025',
      timeAfter: '12:00',
      timeBefore: '23:59',
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

    cy.get('[data-testid="tax-included-previous"]').contains('72,00 €')
  })
})
