context('Dispatch', () => {
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

      cy.intercept('POST', '/api/tasks').as('postTask')
      cy.intercept('POST', '/admin/task-lists/**/jane').as('postTaskList')

      cy.visit('/login')

      cy.get('[name="_username"]').type('admin')
      cy.get('[name="_password"]').type('12345678')
      cy.get('[name="_submit"]').click()

      cy.visit('/admin/dashboard')

      cy.location('pathname').should('eq', '/admin/dashboard')

      cy.wait(1500)

    })

    it('assign a task with drag n drop', () => {

      //
      // Assign task
      //

     // add a rider tasklist
     cy.get('i[data-cypress-add-to-planning]')
     .click()

      cy.get('.ReactModal__Content--select-courier [data-action="dispatch"] > div')
      .click()

      cy.get('[data-cypress-select-username="jane"]')
      .click()

      cy.get('.ReactModal__Content--select-courier button[type="submit"]')
      .click()

      // FIXME : when you click on "add to planning" it opens the search panel I don't know why
      cy.get('[data-cypress-close-search]')
      .click()

      cy.get('[data-cypress-close-search]')
      .click()

      cy.wait(500)

      cy.get('#accordion .accordion__button').click()
    })
  })