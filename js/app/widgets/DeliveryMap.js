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

  pickup.addTo(map)
  dropoff.addTo(map)

  const layers = [ pickup, dropoff ]

  if (options.hasOwnProperty('polyline')) {
    const polyline = L.polyline(MapHelper.decodePolyline(options.polyline), polylineOptions)
    polyline.addTo(map)
    layers.push(polyline)
  } else {
    MapHelper.getPolyline(pickup, dropoff)
      .then(data => {
        var polyline = new L.Polyline(data, polylineOptions);
        map.addLayer(polyline);
      })
  }

  const pad = Math.sqrt(2) / 2

  if (options.hasOwnProperty('onFitBoundsEnd')) {
    map.once('moveend', () => options.onFitBoundsEnd(map))
  }

  setTimeout(() => MapHelper.fitToLayers(map, layers, pad), 1000)
}
