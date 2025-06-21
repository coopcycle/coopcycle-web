context('Dispatch', () => {
  beforeEach(() => {
    cy.loadFixtures('../cypress/fixtures/dispatch.yml', true)

    cy.intercept('POST', '/api/tasks').as('postTask')
    cy.intercept('POST', '/admin/task-lists/**/jane').as('postTaskList')

    cy.login('admin', '12345678')

    cy.visit('/admin/dashboard')
    cy.urlmatch(/\/admin\/dashboard$/)
  })

  it('creates a task', () => {

    cy.get('[data-rfd-droppable-id="unassigned"] > .taskList__tasks')
      .children()
      .should('have.length', 11)

    cy.get('#map .leaflet-marker-pane > .beautify-marker')
      // For some unknown reason, one marker is not displayed
      .should('have.length', 10)

    //
    // Open task modal
    //

    cy.get('[data-rfd-droppable-id="unassigned"] > .taskList__tasks')
      .children()
      .first()
      .dblclick()

    cy.get('.ReactModal__Content--task-form')
      .should('be.visible')

    cy.get('.ReactModal__Content--task-form .address-autosuggest__container  input[type="search"]')
      .should('have.value', '272, rue Saint Honoré 75001 Paris 1er')

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

    cy.get('[data-testid="more-button"]').click();
    cy.contains('span.ant-dropdown-menu-title-content', 'Créer une tâche autonome (obsolète)').click();
    
    cy.get('.ReactModal__Content--task-form input[type="search"]')
      .type('91 rue de rivoli paris', { timeout: 5000, delay: 30 })

    cy.get('.ReactModal__Content--task-form')
      .find('ul[role="listbox"] li', { timeout: 5000 })
      .contains('91 Rue De Rivoli, 75001 Paris, France')
      .click()

    cy.get('.ReactModal__Content--task-form input[type="search"]')
      .should('have.value', '91 Rue De Rivoli, 75001 Paris, France')

    cy.wait(500)

    cy.get('.ReactModal__Content--task-form .modal-footer .btn-primary')
      .click()

    cy.wait('@postTask')

    cy.get('[data-rfd-droppable-id="unassigned"] > .taskList__tasks')
      .children()
      .should('have.length', 12)

    cy.get('#map .leaflet-marker-pane > .beautify-marker')
      // Same as above, for some unknown reason, one marker is not displayed
      .should('have.length', 11)
  })
})
