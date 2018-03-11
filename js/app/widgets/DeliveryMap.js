import MapHelper from './../MapHelper'
import L from 'leaflet'

const polylineOptions = {
  color: '#3498DB',
  opacity: 0.7
}

export default (selector, options) => {

  const map = MapHelper.init(selector)

  const pickup = MapHelper.createMarker(options.pickup, 'cube', 'marker', '#E74C3C')
  const dropoff = MapHelper.createMarker(options.dropoff, 'flag', 'marker', '#2ECC71')
  const polyline = L.polyline(MapHelper.decodePolyline(options.polyline), polylineOptions)

  pickup.addTo(map)
  dropoff.addTo(map)
  polyline.addTo(map)

  const pad = Math.sqrt(2) / 2

  setTimeout(() => MapHelper.fitToLayers(map, [ pickup, dropoff, polyline ], pad), 1000)

}
