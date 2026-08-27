//
// Helpers for asserting on the dispatch map.
//
// The map does not expose its state through the DOM in any stable way — and once pins
// are rendered on a WebGL canvas it exposes nothing at all — so everything goes through
// the `window.__coopcycleMap` test hook installed by the dispatch map.
// @see js/app/dashboard/components/mapTestHook.js
//

/**
 * Reads a value off the test hook.
 *
 * Built on `.its()` + `.invoke()` rather than `.then()` on purpose: those retry, so a
 * following `.should()` keeps re-reading the map until it settles. A `.then()` would
 * snapshot the value once and assert against whatever the map happened to hold at that
 * instant — which, for anything loaded over the network, is usually nothing.
 */
const readMap = (method, ...args) =>
  cy.window({ log: false }).its('__coopcycleMap').invoke(method, ...args)

/**
 * Waits until the map exists and has settled.
 */
Cypress.Commands.add('waitForMapIdle', () => {
  readMap('isIdle').should('equal', true)
})

Cypress.Commands.add('mapHook', () => {
  cy.waitForMapIdle()

  return cy.window({ log: false }).its('__coopcycleMap')
})

/**
 * IRIs of the tasks currently on the map (hidden ones excluded).
 */
Cypress.Commands.add('mapTaskIris', () => readMap('taskIris'))

/**
 * IRIs of the tasks drawn as an individual pin, i.e. not swallowed by a cluster.
 */
Cypress.Commands.add('mapPaintedTaskIris', () => readMap('paintedTaskIris'))

Cypress.Commands.add('mapClusters', (type = null) => readMap('clusters', type))

Cypress.Commands.add('mapWarehouses', () => readMap('warehouses'))

Cypress.Commands.add('mapSwoopyCount', () => readMap('swoopyCount'))

/**
 * Clicks the pin of a task, wherever it happens to be on screen.
 */
Cypress.Commands.add('clickTaskOnMap', (iri, options = {}) => {
  readMap('project', iri).should('not.equal', null)

  cy.mapHook().then(m => {
    const point = m.project(iri)

    cy.get('#map').click(point.x, point.y, options)
  })
})

Cypress.Commands.add('clickClusterOnMap', (type, index = 0) => {
  cy.mapClusters(type).should('have.length.greaterThan', index)

  cy.mapHook().then(m => {
    const cluster = m.clusters(type)[index]

    cy.get('#map').click(cluster.x, cluster.y)
  })
})

/**
 * Ctrl+drag over a rectangle, which selects every task inside it.
 *
 * Uses real (CDP-driven) mouse input rather than `.trigger()`: with synthetic events
 * `leaflet-area-select` starts the drag and even draws its selection box, but never
 * fires `selectarea:selected`, so nothing ends up selected.
 */
Cypress.Commands.add('boxSelectOnMap', (from, to) => {
  const modifiers = { ctrlKey: true }
  const mid = { x: (from.x + to.x) / 2, y: (from.y + to.y) / 2 }

  // Holding ctrl is what turns map panning off; the plugin does that from its keydown
  // handler. Setting `ctrlKey` on the mouse events alone leaves dragging enabled, and
  // the map then pans along with the drag — the selection stays a single point,
  // because layer coordinates move with the map, and nothing is ever selected.
  cy.document().trigger('keydown', { key: 'Control', ctrlKey: true })

  cy.get('#map').realMouseDown({ ...modifiers, x: from.x, y: from.y })
  cy.get('#map').realMouseMove(mid.x, mid.y, modifiers)
  cy.get('#map').realMouseMove(to.x, to.y, modifiers)
  cy.get('#map').realMouseUp({ ...modifiers, x: to.x, y: to.y })

  cy.document().trigger('keyup', { key: 'Control' })
})

/**
 * Draws a polygon with the lasso control, which selects every task inside it.
 * `points` are container coordinates; the polygon is closed by clicking the first
 * vertex again, which is how the drawing tool ends a shape.
 */
Cypress.Commands.add('lassoSelectOnMap', points => {
  expect(points.length >= 3, 'a polygon needs at least 3 vertices').to.equal(true)

  // The control is a plain Leaflet button: it responds to a regular click, but not to
  // a real one (which lands on the icon inside it and never toggles draw mode). The
  // vertices, on the other hand, have to be real clicks on the map.
  cy.get('[data-testid="map-polygon-select"], .leaflet-pm-icon-polygon')
    .parent()
    .click({ force: true })

  points.forEach(point => {
    cy.get('#map').realClick({ x: point.x, y: point.y })
  })

  // Close the shape on its first vertex
  cy.get('#map').realClick({ x: points[0].x, y: points[0].y })
})
