context('Checkout', () => {
  beforeEach(() => {

    const prefix = Cypress.env('COMMAND_PREFIX')

    let cmd = 'bin/console coopcycle:fixtures:load -f cypress/fixtures/dispatch.yml --env test'
    if (prefix) {
      cmd = `${prefix} ${cmd}`
    }

    cy.exec(cmd)

    cy.window().then((win) => {
      win.sessionStorage.clear()
    })
  })

  it('make basic dispatch operations', () => {

    cy.server()
    cy.route('POST', '/api/tasks').as('postTask')
    cy.route('POST', '/admin/task-lists/**/jane').as('postTaskList')

    cy.visit('/login')

    cy.get('[name="_username"]').type('admin')
    cy.get('[name="_password"]').type('12345678')
    cy.get('[name="_submit"]').click()

    cy.location('pathname').should('eq', '/admin/dashboard')

    cy.get('[data-rbd-droppable-id="unassigned"]')
      .children()
      .should('have.length', 2)

    cy.get('#map .leaflet-marker-pane > .beautify-marker')
      .should('have.length', 2)

    //
    // Open task modal
    //

    cy.get('[data-rbd-droppable-id="unassigned"]')
      .first()
      .dblclick()

    cy.get('.ReactModal__Content--task-form')
      .should('be.visible')

    cy.get('.ReactModal__Content--task-form .address-autosuggest__container  input[type="search"]')
      .should('have.value', '18, avenue Ledru-Rollin 75012 Paris 12ème')

    cy.get('.ReactModal__Content--task-form .modal-header .fa-times')
      .click()

    //
    // Click on marker
    //

    // FIXME
    // Cypress complains the marker element is detached
    // Check if we can avoid re-rendering all the time

    // // @see https://www.cypress.io/blog/2020/07/22/do-not-get-too-detached/
    // // @see https://on.cypress.io/element-has-detached-from-dom
    // cy.get('#map .leaflet-marker-pane .marker')
    //   .eq(0)
    //   .click()

    // cy.get('#map .leaflet-popup-pane > .leaflet-popup .leaflet-popup-close-button')
    //   .click()

    //
    // Create a task
    //

    cy.get('.dashboard__aside .dashboard__panel:first-child .pull-right > a:first-child')
      .click()

    cy.get('.ReactModal__Content--task-form input[type="search"]')
      .type('91 rue de rivoli paris', { timeout: 5000, delay: 30 })

    cy.get('.ReactModal__Content--task-form')
      .find('ul[role="listbox"] li', { timeout: 5000 })
      .contains('91 Rue de Rivoli, Paris, France')
      .click()

    cy.get('.ReactModal__Content--task-form input[type="search"]')
      .should('have.value', '91 Rue de Rivoli, Paris, France')

    // FIXME
    // Make it work without wait
    cy.wait(500)

    cy.get('.ReactModal__Content--task-form .modal-footer .btn-primary')
      .click()

    cy.wait('@postTask')

    cy.get('[data-rbd-droppable-id="unassigned"]')
      .children()
      .should('have.length', 3)

    cy.get('#map .leaflet-marker-pane > .beautify-marker')
      .should('have.length', 3)

    //
    // Assign task
    //

    cy.get('.dashboard__aside .dashboard__panel:nth-child(2) a.pull-right')
      .click()

    cy.get('.ReactModal__Content--select-courier')
      .should('be.visible')

    cy.get('.ReactModal__Content--select-courier .form-group > div')
      .click()

    cy.get('.ReactModal__Content--select-courier [class$="-MenuList"] [class$="-option"]')
      .contains('jane')
      .click()

    // FIXME
    // Make it work without wait
    cy.wait(500)

    cy.get('.ReactModal__Content--select-courier .modal-footer .btn-primary')
      .click()

    cy.wait('@postTaskList', { timeout: 10000 })

    // FIXME Drag'n'drop doesn't work

    // cy
    //   .get('[data-rbd-droppable-id="unassigned"]')
    //   .children()
    //   .first()
    //   .drag('[data-rbd-droppable-id="assigned:jane"]')

    // cy.wait('@postTaskList', { timeout: 10000 })

  })
})
