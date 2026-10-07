import L from 'leaflet'

// maplibre-gl can't be loaded in jsdom, stub the Leaflet bridge
jest.mock('@maplibre/maplibre-gl-leaflet', () => {
  const L = require('leaflet')
  L.maplibreGL = jest.fn(() => new L.Layer())
})

describe('BaseMap', () => {

  let getContext

  beforeEach(() => {
    getContext = jest.spyOn(HTMLCanvasElement.prototype, 'getContext')
  })

  afterEach(() => {
    getContext.mockRestore()
  })

  // isWebGL2Supported() caches its result, so load a fresh module each time
  const loadBaseMap = () => {
    let BaseMap
    jest.isolateModules(() => {
      BaseMap = require('../BaseMap')
    })
    return BaseMap
  }

  it('falls back to OSM raster tiles without WebGL2', () => {
    getContext.mockReturnValue(null)

    const { createBaseMapLayer, isWebGL2Supported } = loadBaseMap()

    expect(isWebGL2Supported()).toBe(false)

    const layer = createBaseMapLayer()
    expect(layer).toBeInstanceOf(L.TileLayer)
    expect(layer._url).toBe('https://tile.openstreetmap.org/{z}/{x}/{y}.png')
    expect(L.maplibreGL).not.toHaveBeenCalled()
  })

  it('falls back to OSM raster tiles when getContext throws', () => {
    getContext.mockImplementation(() => { throw new Error('Nope') })

    const { createBaseMapLayer } = loadBaseMap()

    expect(createBaseMapLayer()).toBeInstanceOf(L.TileLayer)
  })

  it('uses MapLibre with WebGL2', () => {
    const loseContext = jest.fn()
    getContext.mockReturnValue({
      getExtension: () => ({ loseContext }),
    })

    const { createBaseMapLayer, isWebGL2Supported } = loadBaseMap()

    expect(isWebGL2Supported()).toBe(true)
    expect(loseContext).toHaveBeenCalled()

    const layer = createBaseMapLayer()
    expect(layer).not.toBeInstanceOf(L.TileLayer)
    expect(L.maplibreGL).toHaveBeenCalled()
  })

  it('adds the raster fallback to the map', () => {
    getContext.mockReturnValue(null)

    const { addBaseMapLayer } = loadBaseMap()

    const container = document.createElement('div')
    document.body.appendChild(container)
    const map = L.map(container).setView([48.85, 2.35], 13)

    const layer = addBaseMapLayer(map)

    expect(layer).toBeInstanceOf(L.TileLayer)
    expect(map.hasLayer(layer)).toBe(true)
  })
})
