import MapHelper from '../MapHelper'
import L from 'leaflet'

const polylineOptions = {
  color: '#3498DB',
  opacity: 0.7
}

const circleMarkerOptions = {
  radius: 3,
  stroke: false,
  fill: true,
  fillColor: '#2980B9',
  fillOpacity: 0.8
}

const map = MapHelper.init('positions-map')

const latLngs = window.AppData.positions.map(position => [position.coordinates.latitude, position.coordinates.longitude])

if (latLngs.length > 0) {
  var polyline = L.polyline(latLngs, polylineOptions).addTo(map)
  latLngs.forEach(latLng => {
    L.circleMarker(latLng, circleMarkerOptions).addTo(map)
  })

  map.fitBounds(polyline.getBounds())
}
