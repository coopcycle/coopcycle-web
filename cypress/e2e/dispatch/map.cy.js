//
// Regression net for the dispatch map.
//
// Everything here goes through `window.__coopcycleMap` rather than through the DOM,
// so that these specs keep meaning the same thing once the map stops rendering its
// pins as DOM elements.
// @see js/app/dashboard/components/mapTestHook.js
//

const enableClusters = () => {
  cy.get('[data-testid="settings-button"]').first().click({ force: true })

  cy.contains('.ant-form-item', 'Activer les clusters')
    .contains('.ant-radio-button-wrapper', 'Oui')
    .click()

  cy.get('.ReactModal__Content--settings .btn-primary').click()

  cy.get('.ReactModal__Content--settings').should('not.exist')
}

/**
 * A rectangle in container coordinates, centered on the first task pin.
 */
const boxAround = (point, half = 25) => ({
  from: { x: point.x - half, y: point.y - half },
  to: { x: point.x + half, y: point.y + half },
})

const selectedTaskIds = () =>
  cy.get('.task__highlighted').then($els =>
    Cypress._.map($els.toArray(), el => el.getAttribute('data-task-id')))

context('Dispatch map', () => {

  describe('with the default fixtures', () => {

    before(() => {
      cy.loadFixtures('dispatch_dashboard.yml', true)
    })

    beforeEach(() => {
      cy.login('admin', '12345678')

      cy.visit('/admin/dashboard')
      cy.urlmatch(/\/admin\/dashboard$/)

      cy.waitForMapIdle()
    })

    it('puts every unassigned task on the map', () => {

      cy.get('[data-rfd-droppable-id="unassigned"] > .taskList__tasks')
        .children()
        .should('have.length', 11)

      cy.mapTaskIris().should('have.length', 11)

      // Clusters are off by default
      cy.mapClusters('task').should('have.length', 0)

      // The tasks picked up at the restaurant address are grouped together
      cy.mapClusters('pickup').should('have.length', 1)
      cy.mapClusters('pickup').its(0).its('count').should('equal', 4)

      // 10, not 11: this is the count the specs used to assert on `.beautify-marker`
      // elements, with a comment wondering why one marker was missing. Pinned here as
      // observed behaviour so that a change in it is caught, not explained away.
      cy.mapPaintedTaskIris().should('have.length', 10)
    })

    it('groups the pins into clusters when clusters are enabled', () => {

      cy.mapClusters('task').should('have.length', 0)

      enableClusters()

      cy.mapClusters('task').should('have.length.greaterThan', 0)

      // Tasks are still known to the map, they are just not drawn individually
      cy.mapTaskIris().should('have.length', 11)
      cy.mapPaintedTaskIris().should('have.length.lessThan', 10)
    })

    it('opens the task popup when clicking a pin', () => {

      cy.mapPaintedTaskIris().should('have.length', 10)

      cy.mapPaintedTaskIris().then(iris => {
        cy.clickTaskOnMap(iris[0])
      })

      cy.get('[data-testid="task-popup"]').should('be.visible')
    })

    it('opens the group popup when clicking a pickup cluster', () => {

      // The tasks picked up at a restaurant address are grouped together
      cy.mapClusters('pickup').should('have.length.greaterThan', 0)

      cy.clickClusterOnMap('pickup')

      cy.get('[data-testid="pickup-group-popup"]').should('be.visible')

      // One row per task in the group
      cy.get('[data-testid="pickup-group-popup"] table tbody tr')
        .should('have.length', 4)
    })

    it('points to the next task when hovering a row of the group popup', () => {

      cy.clickClusterOnMap('pickup')
      cy.get('[data-testid="pickup-group-popup"]').should('be.visible')

      cy.mapSwoopyCount().should('equal', 0)

      // The last row, deliberately: the arrow drawn for a row higher up lands across the
      // popup, under the pointer, which takes the hover off the row and clears the arrow
      // again in the same breath. The one for the last row points clear of it.
      cy.get('[data-testid="pickup-group-popup"] table tbody tr')
        .last()
        .realHover({ scrollBehavior: false })

      cy.mapSwoopyCount().should('equal', 1)

      // Moving away from the popup takes the arrow down again
      cy.get('#map').realMouseMove(10, 10)

      cy.mapSwoopyCount().should('equal', 0)
    })

    it('selects the tasks inside the box when ctrl+dragging', () => {

      // Make sure the tasks have arrived before reading their coordinates
      cy.mapPaintedTaskIris().should('have.length', 10)

      cy.mapHook().then(m => {
        const iris = m.paintedTaskIris()
        const box = boxAround(m.project(iris[0]))

        const expected = iris.filter(iri => {
          const p = m.project(iri)

          return p.x >= box.from.x && p.x <= box.to.x
            && p.y >= box.from.y && p.y <= box.to.y
        })

        expect(expected.length, 'tasks inside the box').to.be.greaterThan(0)

        cy.boxSelectOnMap(box.from, box.to)

        selectedTaskIds().should(ids => {
          expect(ids.sort()).to.deep.equal(expected.slice().sort())
        })
      })
    })

    it('selects the tasks inside the polygon drawn with the lasso', () => {

      cy.mapPaintedTaskIris().should('have.length', 10)

      cy.mapHook().then(m => {
        const iris = m.paintedTaskIris()
        const box = boxAround(m.project(iris[0]), 40)

        const expected = iris.filter(iri => {
          const p = m.project(iri)

          return p.x >= box.from.x && p.x <= box.to.x
            && p.y >= box.from.y && p.y <= box.to.y
        })

        expect(expected.length, 'tasks inside the polygon').to.be.greaterThan(0)

        cy.lassoSelectOnMap([
          { x: box.from.x, y: box.from.y },
          { x: box.to.x,   y: box.from.y },
          { x: box.to.x,   y: box.to.y },
          { x: box.from.x, y: box.to.y },
        ])

        selectedTaskIds().should(ids => {
          expect(ids.sort()).to.deep.equal(expected.slice().sort())
        })
      })
    })
  })

  describe('with warehouses', () => {

    before(() => {
      cy.loadFixtures('dispatch_dashboard_warehouse.yml', true)
    })

    beforeEach(() => {
      cy.login('admin', '12345678')

      cy.visit('/admin/dashboard')
      cy.urlmatch(/\/admin\/dashboard$/)

      cy.waitForMapIdle()
    })

    it('colors a warehouse pin depending on how many tasks it holds', () => {

      cy.mapWarehouses().should('have.length', 2)

      cy.mapWarehouses().then(warehouses => {
        const withTasks = warehouses.find(w => w.name === 'Entrepot Nord')
        const empty     = warehouses.find(w => w.name === 'Entrepot Sud')

        // address_3 is used by task_3 and task_10
        expect(withTasks.taskCount, 'tasks held by Entrepot Nord').to.equal(2)
        expect(withTasks.color, 'Entrepot Nord is green').to.equal('#27AE60')

        expect(empty.taskCount, 'tasks held by Entrepot Sud').to.equal(0)
        expect(empty.color, 'Entrepot Sud is grey').to.equal('#95A5A6')
      })

      // A warehouse swallows the pins of the tasks at its address
      cy.mapPaintedTaskIris().should('have.length.lessThan', 10)
    })
  })
})
