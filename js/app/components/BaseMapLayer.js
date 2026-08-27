import { useEffect } from 'react'
import { useMap } from 'react-leaflet'

import { addBaseMapLayer } from '../BaseMap'

/**
 * react-leaflet drop-in replacement for <TileLayer />, rendering the
 * OpenFreeMap basemap with MapLibre GL JS.
 */
const BaseMapLayer = ({ style }) => {
  const map = useMap()

  useEffect(() => {
    if (!map) {
      return
    }

    const layer = addBaseMapLayer(map, { style })

    return () => {
      map.removeLayer(layer)
    }
  }, [map, style])

  return null
}

export default BaseMapLayer
