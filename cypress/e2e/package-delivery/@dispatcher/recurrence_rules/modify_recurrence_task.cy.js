describe('Delivery with recurrence rule (role: dispatcher)', () => {
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

  it('modify recurrence task data', function () {
    const createOrderWithRecurrence = () => {
      cy.log('Create order with recurrence')

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

      cy.get('[data-testid="recurrence-add"]').click()
      cy.chooseDaysOfTheWeek([5, 6])
      cy.get('[data-testid=save]').click()

      cy.get('button[type="submit"]').click()
    }
    createOrderWithRecurrence()

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    cy.get('a[href*="recurrence-rules"]').click()

    // Recurrence rule page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/recurrence-rules\/[0-9]+$/)

    const modifyTaskRelatedData = () => {
      cy.log('Modify task related data')

      cy.betaEnterAddressAtPosition(
        0,
        '24 Rue de la Paix, 75002 Paris, France',
        /^24,? Rue de la Paix,? 75002,? Paris,? France/i,
        'Point 1',
        '+33110101010',
        'Name 1',
      )

      cy.get('[data-testid="form-task-0"]').within(() => {
        cy.antdSelect('.ant-select[data-testid="select-after"]', '10:00')
        cy.antdSelect('.ant-select[data-testid="select-before"]', '12:00')
      })

      cy.betaEnterCommentAtPosition(0, 'Comment 1')

      // tags
      cy.get(`[data-testid="form-task-0"]`).within(() => {
        cy.get('[aria-label="Remove Important"]').click()
        cy.get(`[data-testid=tags-select]`).click()
      })
      cy.get('#react-select-2-option-1').click()

      cy.betaEnterAddressAtPosition(
        1,
        '44 Rue de Rivoli, 75004 Paris, France',
        /^44,? Rue de Rivoli,? 75004,? Paris,? France/i,
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

      // tags
      cy.get(`[data-testid="form-task-1"]`).within(() => {
        cy.get('[aria-label="Remove Perishable"]').click()
        cy.get(`[data-testid=tags-select]`).click()
      })
      cy.get('#react-select-3-option-0').click()
    }
    modifyTaskRelatedData()

    cy.get('button[type="submit"]').click()

    // Recurrence rules list
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/recurrence-rules$/)
    cy.get('[data-testid=recurrence-list-item]')
      .find('[data-testid="recurrence-edit"]')
      .click()

    // Recurrence rule page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/recurrence-rules\/[0-9]+$/)

    //verify that all the fields are saved correctly

    cy.betaTaskShouldHaveValue({
      taskFormIndex: 0,
      addressName: 'Point 1',
      telephone: '01 10 10 10 10',
      contactName: 'Name 1',
      address: /^24,? Rue de la Paix,? 75002,? Paris,? France/i,
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
      address: /^44,? Rue de Rivoli,? 75004,? Paris,? France/i,
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

    cy.get('[data-testid="tax-included"]').contains('4,99 €')

    cy.get('[data-testid="recurrence-container"]').contains(
      'chaque semaine le vendredi, samedi',
    )
  })
})
