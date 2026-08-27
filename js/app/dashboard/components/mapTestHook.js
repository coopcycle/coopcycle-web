/**
 * Test hook for the dispatch map.
 *
 * The Cypress specs used to assert on `.beautify-marker` DOM nodes. That only works
 * as long as markers *are* DOM nodes; once the map renders its pins on a WebGL canvas
 * there is nothing to query. This hook is the stable contract in between: it is
 * implemented by the Leaflet proxy today, and must be implemented identically by the
 * MapLibre proxy, so the same specs gate both.
 *
 * Every method below has to be implemented by any map proxy passed to
 * `installMapTestHook()`:
 *
 *   testIsIdle()          bool      map is ready and has nothing pending to draw
 *   testTaskIris()        string[]  tasks currently on the map (hidden ones excluded)
 *   testPaintedTaskIris() string[]  tasks drawn as an individual pin (not clustered away)
 *   testProject(iri)      {x,y}|null   container coordinates of the centre of a drawn
 *                                      task pin, i.e. where a click lands on it
 *   testClusters(type)    [{ type, x, y, count }]  type: 'task'|'pickup'|'warehouse',
 *                                                   filtered by `type` when given
 *   testWarehouses()      [{ iri, name, taskCount, color }]
 *   testSwoopyCount()     number    "point to next task" arrows currently drawn
 *   testContainer()       Element   the map container
 */
export function installMapTestHook(proxy) {

  // Never expose the internals of the map in a production build.
  if (process.env.NODE_ENV === 'production') {
    return
  }

  window.__coopcycleMap = {
    isIdle:          () => proxy.testIsIdle(),
    taskIris:        () => proxy.testTaskIris(),
    paintedTaskIris: () => proxy.testPaintedTaskIris(),
    project:         iri => proxy.testProject(iri),
    clusters:        type => proxy.testClusters(type),
    warehouses:      () => proxy.testWarehouses(),
    swoopyCount:     () => proxy.testSwoopyCount(),
    container:       () => proxy.testContainer(),
  }
}
