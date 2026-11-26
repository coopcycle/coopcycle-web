context('Import tasks (role: admin)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup(['user_admin.yml', 'store_w_time_slot_pricing.yml'])

    cy.setMockDateTime('2019-12-12 8:00:00')

    cy.login('admin', '12345678')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  const uploadTaskFile = file => {
    cy.visit('/admin/deliveries')

    cy.get('[data-target="#import-modal"]').click()

    // A file of tasks is imported for a day, where a file of deliveries goes to a store
    cy.get('[data-import-kind] input[value="tasks"]').check()
    cy.get('[data-import-kind-field="tasks"]').should('be.visible')
    cy.get('[data-import-kind-field="deliveries"]').should('not.be.visible')
    cy.get('[data-import-kind-field="tasks"] input').should(
      'have.value',
      '2019-12-12',
    )

    cy.get('input[type=file]').selectFile(file)
    cy.get('[data-testid="import-submit"]').click()

    cy.urlmatch(/\/admin\/imports\/\d+\/mapping$/)
  }

  const confirmMapping = () => {
    cy.get('form[data-column-mapping] button[type=submit]:not([name])').click()

    cy.wait(1000)
    cy.urlmatch(/\/admin\/deliveries$/)
    cy.urlmatch('section=imports', 'include', 'search')
  }

  it('imports a file of tasks', function () {
    uploadTaskFile('fixtures/csv/tasks.csv')

    // The file uses our own column names, so they are all recognised
    cy.get('[data-column-mapping-choice="0"]').should('have.value', 'type')
    cy.get('[data-column-mapping-choice="1"]').should('have.value', 'address')
    cy.get('[data-column-mapping-choice="7"]').should('have.value', 'timeslot')
    cy.get('[data-column-mapping-choice="9"]').should('have.value', 'weight')

    confirmMapping()

    cy.consumeMessages(30)

    cy.reload()

    // Listed as a task import, next to the delivery ones
    cy.get('[data-import-type="tasks"]').should('have.length', 1)
    cy.get('[data-import-type="tasks"] i.fa-check[data-import-status]').should(
      'exist',
    )

    // The tasks are on the board of the day the file was imported for, in the groups
    // it named: the one the file gives, and one named after the import for the row
    // that gives none
    cy.intercept('GET', '/api/tasks**').as('boardTasks')
    cy.visit('/admin/dashboard/fullscreen/2019-12-12')
    cy.wait('@boardTasks')

    // The board's own bundle takes a while to come up the first time
    cy.get('[data-rfd-droppable-id="unassigned_groups"] .taskList__tasks', {
      timeout: 30000,
    })
      .children()
      .should('have.length', 2)

    cy.get('[data-rfd-droppable-id="unassigned_groups"]').should(
      'contain',
      'Morning round',
    )
  })

  /**
   * The column names say nothing, so the detector leaves the address for the dispatcher:
   * an address can't be told from its values, and a file with none at all is of no use.
   */
  it('imports a file of tasks whose columns are named anything at all', function () {
    uploadTaskFile('fixtures/csv/tasks-custom-headers.csv')

    cy.get('[data-column-mapping-choice="0"]').should('have.value', 'ref')
    cy.get('[data-column-mapping-choice="2"]').should('have.value', 'timeslot')
    cy.get('[data-column-mapping-choice="3"]').should('have.value', 'weight')
    // "Point de passage" says nothing, and an address can't be told from its values
    cy.get('[data-column-mapping-choice="1"]').should('have.value', '')

    // Neither an address nor coordinates: there is nowhere for the tasks to go
    cy.get('form[data-column-mapping] button[type=submit]:not([name])').click()
    cy.urlmatch(/\/admin\/imports\/\d+\/mapping$/)
    cy.get('.alert-danger').should('exist')

    cy.get('[data-column-mapping-choice="1"]').select('address')

    confirmMapping()

    cy.consumeMessages(30)

    cy.reload()
    cy.get('[data-import-type="tasks"] i.fa-check[data-import-status]').should(
      'exist',
    )
  })

  it('cancels the tasks an import created', function () {
    uploadTaskFile('fixtures/csv/tasks.csv')
    confirmMapping()

    cy.consumeMessages(30)

    cy.reload()

    cy.get('[data-import-type="tasks"] [data-import-cancel-items-button]')
      .should('be.visible')
      .click()

    // The confirmation says what it is about to cancel, and how many
    cy.get('.ant-modal-confirm-title').should('contain', '3')
    cy.get('.ant-modal-confirm-btns .ant-btn-dangerous').click()

    cy.consumeMessages(30)

    cy.reload()

    // Shown as an import with nothing left of it, and saying how many went
    cy.get('[data-import-type="tasks"] i.fa-ban[data-import-status]').should('exist')
    cy.get('[data-import-type="tasks"] [data-import-progress]').should('contain', '3')
  })
})
