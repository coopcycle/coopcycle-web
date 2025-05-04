context('Dispatch', () => {
  beforeEach(() => {
    cy.loadFixtures('dispatch.yml')

    cy.intercept('POST', '/api/tasks').as('postTask')
    cy.intercept('POST', '/admin/task-lists/**/jane').as('postTaskList')

    cy.login('admin', '12345678')

    cy.visit('/admin/dashboard')
    cy.urlmatch(/\/admin\/dashboard$/)
  })

  it('assign a task with drag n drop', () => {
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
