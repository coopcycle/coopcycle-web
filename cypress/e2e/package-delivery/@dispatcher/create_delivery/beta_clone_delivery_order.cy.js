context('Delivery (role: admin)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup([
      'ORM/user_admin.yml',
      'ORM/tags.yml',
      '../features/fixtures/ORM/store_default.yml',
    ])

    cy.setMockDateTime('2025-04-23 8:30:00')

    cy.login('admin', '12345678')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  it('[beta form] clone delivery order', function () {
    cy.visit('/admin/stores')

    // List of stores
    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une livraison')
      .click()

    // Create delivery page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries\/new$/)
    cy.get('body > div.content > div > div > div > a')
      .contains('click here')
      .click()

    // Create delivery page (new)
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries\/new\/beta$/)

    // Pickup
    cy.betaChooseSavedAddressAtPosition(0, 1)

    cy.get(`[name="tasks[0].comments"]`).type('Pickup comments')

    cy.get(`[data-testid="form-task-0"]`).within(() => {
      cy.get(`[data-testid=tags-select]`).click()
    })
    cy.get('#react-select-3-option-0').click()

    // Dropoff
    cy.betaChooseSavedAddressAtPosition(1, 2)

    cy.get(`[data-testid="form-task-1"]`).within(() => {
      cy.get(
        '[data-testid="/api/packages/1"] > .packages-item__quantity > :nth-child(3)',
      ).click()
    })
    cy.get(`[name="tasks[1].weight"]`).type(2.5)

    cy.get(`[name="tasks[1].comments"]`).type('Dropoff comments')

    cy.get(`[data-testid="form-task-1"]`).within(() => {
      cy.get(`[data-testid=tags-select]`).click()
    })
    cy.get('#react-select-5-option-2').click()

    cy.get('button[type="submit"]').click()

    // list of deliveries page
    // TODO : check for proper redirect when implemented
    // cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries$/)

    cy.urlmatch(/\/admin\/deliveries$/)

    // Advance to the next day to test that the time range is correct
    cy.setMockDateTime('2025-04-24 12:30:00')

    cy.get('[data-testid="delivery__list_item"]')
      .find('[data-testid="order_id"]')
      .contains('1')
      .click()

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)
    cy.get('[data-testid="order_clone"]').click()

    // Create delivery page (new)
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries\/new\/beta$/)

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

    cy.get('[data-testid="tax-included"]').contains('4,99 €')

    cy.get('button[type="submit"]').click()

    // list of deliveries page
    // TODO : check for proper redirect when implemented
    // cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries$/)

    cy.urlmatch(/\/admin\/deliveries$/)

    cy.get('[data-testid="delivery__list_item"]')
      .find('[data-testid="delivery_id"]')
      .click()

    // Edit Delivery page
    // hardcode the delivery id to make sure that we are on the right page (cloned order)
    cy.urlmatch(/\/admin\/deliveries\/2$/)
    cy.get('body > div.content > div > div > div > a')
      .contains('click here')
      .click()

    // Edit Delivery page (new)
    cy.urlmatch(/\/admin\/deliveries\/2\/beta$/)
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

    cy.get('[data-testid="tax-included-previous"]').contains('4,99 €')
  })
})
