context('Delivery (role: admin)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup([
      'ORM/user_admin.yml',
      'ORM/tags.yml',
      'ORM/store_advanced.yml',
    ])
    cy.setMockDateTime('2025-04-23 8:30:00')
    cy.login('admin', '12345678')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  it('modify delivery', function () {
    cy.visit('/admin/stores/1/deliveries/new')

    // Pickup

    cy.betaChooseSavedAddressAtPosition(0, 1)

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

    cy.get(`[data-testid="form-task-1"]`).within(() => {
      cy.get(
        '[data-testid="/api/packages/1"] > .packages-item__quantity > :nth-child(3)',
      ).click()
    })
    cy.betaEnterWeightAtPosition(1, 2.5)

    cy.betaEnterCommentAtPosition(1, 'Dropoff comments')

    cy.get(`[data-testid="form-task-1"]`).within(() => {
      cy.get(`[data-testid=tags-select]`).click()
    })
    cy.get('#react-select-5-option-2').click()

    cy.get('[data-testid="tax-included"]').contains('4,99 €')

    cy.get('button[type="submit"]').click()

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    cy.get('[data-testid="order-edit"]').click()

    // Edit Delivery page
    cy.urlmatch(/\/admin\/deliveries\/[0-9]+$/)

    //verify all fields BEFORE modifications

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

    // Modify data

    cy.betaEnterAddressAtPosition(
      0,
      '72 Rue Saint-Maur, 75011 Paris, France',
      /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
      'Point 1',
      '+33110101010',
      'Name 1',
    )

    cy.get('[data-testid="form-task-0"]').within(() => {
      cy.antdSelect('.ant-select[data-testid="select-after"]', '10:00')
      cy.antdSelect('.ant-select[data-testid="select-before"]', '12:00')
    })

    cy.betaEnterCommentAtPosition(0, 'Comment 1')

    cy.get(`[data-testid="form-task-0"]`).within(() => {
      cy.get('[aria-label="Remove Important"]').click()
      cy.get(`[data-testid=tags-select]`).click()
    })
    cy.get('#react-select-3-option-1').click()

    cy.betaEnterAddressAtPosition(
      1,
      '23 Avenue Claude Vellefaux, 75010 Paris, France',
      /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
      'Point 2',
      '+33120202020',
      'Name 2',
    )

    cy.get('[data-testid="form-task-1"]').within(() => {
      cy.antdSelect('.ant-select[data-testid="select-after"]', '12:00')
      cy.antdSelect('.ant-select[data-testid="select-before"]', '14:00')
    })

    cy.get(`[data-testid="form-task-1"]`).within(() => {
      cy.get(
        '[data-testid="/api/packages/1"] > .packages-item__quantity > :nth-child(1)',
      ).click()
      cy.get(
        '[data-testid="/api/packages/2"] > .packages-item__quantity > :nth-child(3)',
      ).click()
    })

    cy.betaEnterWeightAtPosition(1, 1.5)

    cy.betaEnterCommentAtPosition(1, 'Comment 2')

    cy.get(`[data-testid="form-task-1"]`).within(() => {
      cy.get('[aria-label="Remove Perishable"]').click()
      cy.get(`[data-testid=tags-select]`).click()
    })
    cy.get('#react-select-5-option-0').click()

    cy.get('button[type="submit"]').click()

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    cy.get('[data-testid="order-edit"]').click()

    // Edit Delivery page
    cy.urlmatch(/\/admin\/deliveries\/[0-9]+$/)

    //verify all fields AFTER modifications

    cy.betaTaskShouldHaveValue({
      taskFormIndex: 0,
      addressName: 'Point 1',
      telephone: '01 10 10 10 10',
      contactName: 'Name 1',
      address: /72,? Rue Saint-Maur,? 75011,? Paris,? France/,
      date: '23 avril 2025',
      timeAfter: '10:00',
      timeBefore: '12:00',
      comments: 'Comment 1',
      tags: ['Fragile'],
    })

    cy.betaTaskShouldHaveValue({
      taskFormIndex: 1,
      addressName: 'Point 2',
      telephone: '01 20 20 20 20',
      contactName: 'Name 2',
      address: /23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/,
      date: '23 avril 2025',
      timeAfter: '12:00',
      timeBefore: '14:00',
      packages: [
        {
          nodeId: '/api/packages/2',
          quantity: 1,
        },
      ],
      weight: 1.5,
      comments: 'Comment 2',
      tags: ['Important'],
    })
  })
})
