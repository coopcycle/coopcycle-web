import L from 'leaflet'
import '@maplibre/maplibre-gl-leaflet'
import 'maplibre-gl/dist/maplibre-gl.css'

/**
 * Basemap rendered with MapLibre GL JS, using OpenFreeMap vector tiles.
 * OpenFreeMap is free & requires no API key.
 *
 * https://maplibre.org/projects/gl-js/
 * https://openfreemap.org/
 */

// "liberty" is the closest match to the CARTO Voyager basemap we used before,
// "positron" is the muted, low-contrast one (used where overlays carry the data).
export const STYLES = {
  liberty: 'https://tiles.openfreemap.org/styles/liberty',
  bright: 'https://tiles.openfreemap.org/styles/bright',
  positron: 'https://tiles.openfreemap.org/styles/positron',
}

export const DEFAULT_STYLE = 'positron'

// The GL layer has no maxZoom of its own (vector tiles are overzoomed), so this
// has to be set on the Leaflet map itself, where the tile layer used to do it.
export const MAX_ZOOM = 19

export const ATTRIBUTION =
  '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, tiles by <a href="https://openfreemap.org/">OpenFreeMap</a>'

export function styleUrl(style = DEFAULT_STYLE) {
  return STYLES[style] || style
}

// Raster basemap used when MapLibre can't run, i.e when the browser has no WebGL2.
// https://operations.osmfoundation.org/policies/tiles/
const RASTER_TILES_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
const RASTER_ATTRIBUTION =
  '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'

let webGL2Supported = null

/**
 * MapLibre GL JS v5 requires WebGL2, and throws when it can't get a context.
 * Some machines don't provide it (e.g old Macs, where Firefox reports
 * "GL_SHADING_LANGUAGE_VERSION: 120 < 150"), and the error took down whole pages.
 */
export function isWebGL2Supported() {
  if (null === webGL2Supported) {
    try {
      const canvas = document.createElement('canvas')
      const gl = canvas.getContext('webgl2')
      webGL2Supported = Boolean(gl)
      // Browsers cap the number of live WebGL contexts, release this one right away.
      gl?.getExtension('WEBGL_lose_context')?.loseContext()
    } catch (e) {
      webGL2Supported = false
    }
  }

  return webGL2Supported
}

function createRasterBaseMapLayer() {
  return L.tileLayer(RASTER_TILES_URL, {
    maxZoom: MAX_ZOOM,
    attribution: RASTER_ATTRIBUTION,
  })
}

/**
 * Creates the basemap layer, to be added to a Leaflet map.
 * Behaves like the L.tileLayer it replaces, so all the Leaflet plugins
 * (Geoman, markercluster, arrowheads…) keep working on top of it.
 */
export function createBaseMapLayer(options = {}) {
  if (!isWebGL2Supported()) {
    return createRasterBaseMapLayer()
  }

  const { style, ...rest } = options

  return L.maplibreGL({
    style: styleUrl(style),
    // Leaflet animates a zoom by CSS-scaling the already rendered GL canvas.
    // Zooming out scales it below 1, so anything the canvas no longer covers
    // shows the empty map container. The layer renders `padding` extra around
    // the viewport on each side; 0.5 makes the canvas twice the viewport, which
    // is exactly what a one-level zoom out needs to stay covered.
    // Raising this costs GPU: the rendered area grows with the square of it.
    padding: 0.5,
    // The GL map has its own attribution control, which we disable in favor of
    // Leaflet's one.
    attributionControl: { customAttribution: ATTRIBUTION },
    ...rest,
  })
}

/**
 * Removes the 3D building extrusions the OpenFreeMap styles enable from zoom 14
 * on. They get in the way when dispatching, and hide the streets underneath.
 * The flat "building" fill layer stays, so footprints are still drawn.
 */
function removeBuildingExtrusions(glMap) {
  glMap
    .getStyle()
    .layers.filter(layer => layer.type === 'fill-extrusion' || layer.id === 'building')
    .forEach(layer => glMap.removeLayer(layer.id))
}

/**
 * Paints the Leaflet container in the basemap's own background color, instead
 * of Leaflet's default grey. Whatever the GL canvas does not cover during a
 * zoom animation then blends into the map rather than flashing grey.
 */
function syncBackgroundColor(map, glMap) {
  const background = glMap
    .getStyle()
    .layers.find(layer => layer.type === 'background')

  const color = background?.paint?.['background-color']

  // Styles are free to make this a zoom-dependent expression; only a plain
  // color can be handed to CSS.
  if (typeof color === 'string') {
    map.getContainer().style.backgroundColor = color
  }
}

export function addBaseMapLayer(map, options = {}) {
  const layer = createBaseMapLayer(options)

  // The raster fallback has no GL map to set up.
  if (layer instanceof L.TileLayer) {
    layer.addTo(map)

    return layer
  }

  // The GL map only exists once the layer has been added, and Leaflet does not
  // necessarily add it straight away: a map with no view yet (no center/zoom,
  // because it gets fitted to its markers later) defers every addLayer() until
  // it has one. Reaching for the GL map right after addTo() therefore works on
  // some maps and returns undefined on others, so wait for the layer's own
  // "add" event, which fires in both cases.
  layer.on('add', () => {
    const glMap = layer.getMaplibreMap()

    const onStyleLoaded = () => {
      removeBuildingExtrusions(glMap)
      syncBackgroundColor(map, glMap)
    }

    if (glMap.isStyleLoaded()) {
      onStyleLoaded()
    }

    // Also on every (re)load of the style, which is when the layers come back.
    glMap.on('style.load', onStyleLoaded)

    // The GL layer only positions its canvas in its private _update(), which it
    // hooks to the map's "move" and "resize" events. Neither fires when the view
    // is already set before the layer is added, leaving the canvas offset and the
    // map blank until the user first pans or zooms. Fire "move" once to place it.
    map.fire('move')
  })

  layer.addTo(map)

  return layer
}

export default createBaseMapLayer
