context('Dispatch', () => {
  beforeEach(() => {
    cy.loadFixtures('dispatch_dashboard.yml', true)

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

    cy.mapTaskIris().should('have.length', 11)

    //
    // Open task modal
    //

    cy.get('[data-rfd-droppable-id="unassigned"] > .taskList__tasks')
      .children()
      .first()
      .dblclick()

    cy.get('.ReactModal__Content--task-form')
      .should('be.visible')

    cy.get('.ReactModal__Content--task-form [role="combobox"] input[type="search"]')
      .should('have.value', '272, rue Saint Honoré 75001 Paris 1er')

    cy.get('.ReactModal__Content--task-form .modal-header .fa-times')
      .click()

    // Clicking a pin to open its popup is covered by dispatch/map.cy.js. It is
    // deliberately not done here: opening the popup in this spec leaves the page in a
    // state that makes the address autosuggest below fail with a Cypress source
    // rewriting error, which is why the assertion was commented out here to begin with.

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

    cy.mapTaskIris().should('have.length', 12)
  })
})
