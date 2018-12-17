import MapHelper from './../MapHelper'
import L from 'leaflet'

const polylineOptions = {
  color: '#3498DB',
  opacity: 0.7
}

export default function(selector, options) {

  // Do not initialize map on mobile devices
  if (!$(`#${selector}`).is(':visible')) {
    return
  }

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
        var polyline = new L.Polyline(data, polylineOptions)
        map.addLayer(polyline)
      })
  }

  const fitBoundsOptions = options.fitBoundsOptions || {}
  const group = new L.featureGroup(layers)
  if (group.getLayers().length > 0) {
    map.fitBounds(group.getBounds().pad(Math.sqrt(2) / 2), fitBoundsOptions)
  }
}
