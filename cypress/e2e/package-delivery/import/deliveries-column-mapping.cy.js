context('Import deliveries with a column mapping (role: admin)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup(['user_admin.yml', 'store_w_time_slot_pricing.yml'])

    cy.setMockDateTime('2019-12-12 8:00:00')

    cy.login('admin', '12345678')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  const uploadFile = file => {
    cy.visit('/admin/deliveries')

    cy.get('[data-target="#import-deliveries-modal"]').click()
    cy.get('input[type=file]').selectFile(file)
    cy.get(
      '#import-deliveries-modal > .modal-dialog > .modal-content > .modal-footer > .btn-primary',
    ).click()

    cy.urlmatch(/\/admin\/deliveries\/imports\/\d+\/mapping$/)
  }

  it('imports a file whose columns are named anything at all', function () {
    uploadFile('fixtures/csv/deliveries-custom-headers.csv')

    // What the names say outright
    cy.get('[data-column-mapping-choice="0"]').should(
      'have.value',
      'external_reference',
    )
    cy.get('[data-column-mapping-choice="2"]').should(
      'have.value',
      'pickup.timeslot',
    )
    cy.get('[data-column-mapping-choice="4"]').should(
      'have.value',
      'dropoff.timeslot',
    )
    cy.get('[data-column-mapping-choice="5"]').should('have.value', 'weight')

    // "Lieu 1" and "Lieu 2" say nothing, and an address can't be told from its
    // values: they are left for the dispatcher, as is the column we have no use for
    cy.get('[data-column-mapping-choice="1"]').should('have.value', '')
    cy.get('[data-column-mapping-choice="3"]').should('have.value', '')
    cy.get('[data-column-mapping-choice="6"]').should('have.value', '')

    // A delivery can't be imported without both addresses
    cy.get('form[data-column-mapping] button[type=submit]:not([name])').click()
    cy.urlmatch(/\/admin\/deliveries\/imports\/\d+\/mapping$/)
    cy.get('.alert-danger').should('exist')

    cy.get('[data-column-mapping-choice="1"]').select('pickup.address')
    cy.get('[data-column-mapping-choice="3"]').select('dropoff.address')

    cy.get('form[data-column-mapping] button[type=submit]:not([name])').click()

    cy.wait(1000)
    cy.urlmatch(/\/admin\/deliveries$/)
    cy.urlmatch('section=imports', 'include', 'search')

    cy.consumeMessages(30)

    cy.reload()
    cy.get('i.fa-check[data-delivery-import-status]').should('exist')

    // The deliveries were read through the mapping, priced and saved
    cy.get('[data-testid="tab:/admin/deliveries"]').click()
    cy.urlmatch(/\/admin\/deliveries$/)

    cy.get('[data-testid=delivery__list_item]').should('have.length', 2)
  })

  it('drops an import whose columns are never matched', function () {
    uploadFile('fixtures/csv/deliveries-custom-headers.csv')

    cy.get('form[data-column-mapping-cancel] button[type=submit]').click()

    cy.urlmatch(/\/admin\/deliveries$/)
    cy.urlmatch('section=imports', 'include', 'search')

    cy.get('i.fa-ban[data-delivery-import-status]').should('exist')
  })
})
