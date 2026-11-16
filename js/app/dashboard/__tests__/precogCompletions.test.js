/**
 * @jest-environment jsdom
 */
// Both ship untranspiled ESM, jest does not transform node_modules, and both
// are imported purely for their side effect on L. Nothing here draws an
// arrowhead or a WebGL basemap.
jest.mock('leaflet-arrowheads', () => ({}))
jest.mock('@maplibre/maplibre-gl-leaflet', () => ({}))

// Task and courier popups: React components this layer never renders. They are
// mocked so the import graph stops here rather than because they are awkward.
jest.mock('../components/CourierPopupContent', () => () => null)
jest.mock('../components/LeafletPopupContent', () => () => null)

import L from 'leaflet'
import MapProxy from '../components/MapProxy'

/**
 * The burst layer against a real Leaflet map.
 *
 * Worth the jsdom map rather than a stub: the things that can go wrong here are
 * Leaflet's, not ours — which pane the marker lands in, whether removing it
 * actually takes the DOM node away, whether a repeat of the same completion
 * stacks a second marker on the first.
 */
const buildProxy = () => {
  const container = document.createElement('div')
  Object.assign(container.style, { width: '800px', height: '600px' })
  document.body.appendChild(container)

  // markercluster refuses to initialise without a maxZoom, which a real map
  // inherits from its tile layer and this one has to be told.
  const map = L.map(container, { center: [ 48.8566, 2.3522 ], zoom: 13, maxZoom: 19 })

  return new MapProxy(map, {
    onEditClick: () => {},
    selectTaskOnMarkerClick: () => {},
    toggleTaskOnMarkerClick: () => {},
    onPickupClusterClick: () => {},
    onTaskMouseDown: () => {},
    onTaskMouseOver: () => {},
    onTaskMouseOut: () => {},
  })
}

const completion = (id = 'abc') => ({ id, latitude: 48.8586, longitude: 2.3470 })

describe('MapProxy precog completions', () => {

  let proxy

  beforeEach(() => { proxy = buildProxy() })
  afterEach(() => { document.body.innerHTML = '' })

  it('draws a mark where a cart was paid for', () => {
    proxy.setPrecogCompletions([ completion() ], true)

    expect(proxy.testPrecogCompletions()).toEqual(['abc'])
    expect(document.querySelectorAll('.precog-burst').length).toBe(1)
    expect(document.querySelector('.precog-burst__ring')).not.toBeNull()
    expect(document.querySelector('.precog-burst__core')).not.toBeNull()
  })

  it('labels it, translated', () => {
    proxy.setPrecogCompletions([ completion() ], true)

    const label = document.querySelector('.precog-burst__label')

    // i18next hands back the key itself when the catalogue is missing an entry,
    // which would put ADMIN_DASHBOARD_PRECOG_ORDER_PLACED on the map.
    expect(label.textContent).toBe('Order placed')
  })

  // Driven by a timer, so the same completion is handed over on every render
  // until it expires. A second marker on the first would double the opacity and
  // read as two orders at one address.
  it('does not stack a second mark on a repeat', () => {
    proxy.setPrecogCompletions([ completion() ], true)
    proxy.setPrecogCompletions([ completion() ], true)
    proxy.setPrecogCompletions([ completion() ], true)

    expect(document.querySelectorAll('.precog-burst').length).toBe(1)
  })

  it('takes the mark off the map when its moment has passed', () => {
    proxy.setPrecogCompletions([ completion() ], true)
    proxy.setPrecogCompletions([], true)

    expect(proxy.testPrecogCompletions()).toEqual([])
    expect(document.querySelectorAll('.precog-burst').length).toBe(0)
  })

  it('keeps marks and halos apart', () => {
    proxy.setPrecogCompletions([ completion('one') ], true)
    proxy.setPrecogCompletions([ completion('two') ], true)

    expect(proxy.testPrecogCompletions()).toEqual(['two'])
    expect(document.querySelectorAll('.precog-burst').length).toBe(1)
  })

  // The layer being switched off has to take the marks with it, not leave the
  // last one frozen on the map.
  it('clears everything when the layer is switched off', () => {
    proxy.setPrecogCompletions([ completion() ], true)
    proxy.setPrecogCompletions([ completion() ], false)

    expect(proxy.testPrecogCompletions()).toEqual([])
    expect(document.querySelectorAll('.precog-burst').length).toBe(0)
  })

  it('renders in the precog pane, under the task markers', () => {
    proxy.setPrecogCompletions([ completion() ], true)

    const pane = document.querySelector('.precog-burst').closest('.leaflet-pane')

    expect(pane.classList.contains('leaflet-precog-pane')).toBe(true)
    expect(Number(pane.style.zIndex)).toBeLessThan(600)
  })
})
