context('Dispatch', () => {
    beforeEach(() => {

      const prefix = Cypress.env('COMMAND_PREFIX')

      let cmd = 'bin/console coopcycle:fixtures:load -f cypress/fixtures/dispatch.yml --env test'
      if (prefix) {
        cmd = `${prefix} ${cmd}`
      }

      cy.exec(cmd)

      cy.intercept('POST', '/api/tasks').as('postTask')
      cy.intercept('POST', '/admin/task-lists/**/jane').as('postTaskList')

      cy.visit('/login')

      cy.login('admin', '12345678')

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

     cy.wait(1000)

      cy.get('.ReactModal__Content--select-courier [data-action="dispatch"] > div')
      .click()

      cy.get('[data-cypress-select-username="jane"]')
      .click()

      cy.get('.ReactModal__Content--select-courier button[type="submit"]')
      .click()

      cy.wait(500)

      cy.contains('jane').click()
    })
  })
