import MapHelper from '../MapHelper'
import L from 'leaflet'
import Centrifuge from 'centrifuge'

import './tracking.scss'

const polylineOptions = {
  color: '#3498DB',
  opacity: 0.7
}

const createIcon = username => {
  const iconUrl = window.Routing.generate('user_avatar', { username })

  return L.icon({
    iconUrl: iconUrl,
    iconSize:    [20, 20], // size of the icon
    iconAnchor:  [10, 10], // point of the icon which will correspond to marker's location
    popupAnchor: [-2, -72], // point from which the popup should open relative to the iconAnchor,
  })
}

let courierMarker

const el = document.querySelector('#delivery')

const { polyline } = el.dataset
const pickup = JSON.parse(el.dataset.pickup)
const dropoff = JSON.parse(el.dataset.dropoff)
const isCompleted = JSON.parse(el.dataset.isCompleted)
const centrifugoToken = JSON.parse(el.dataset.centrifugoToken)
const centrifugoChannel = JSON.parse(el.dataset.centrifugoChannel)

const map = MapHelper.init('map')

const group = new L.featureGroup()

const pickupMarker = MapHelper.createMarker(pickup, 'cube', 'marker', '#0074D9')
const dropoffMarker = MapHelper.createMarker(dropoff, 'arrow-down', 'marker', '#2ECC40')
const polylineMarker = L.polyline(MapHelper.decodePolyline(polyline, polylineOptions))

pickupMarker.addTo(map)
dropoffMarker.addTo(map)
polylineMarker.addTo(map)

group.addLayer(pickupMarker)
group.addLayer(dropoffMarker)
group.addLayer(polylineMarker)

map.fitBounds(group.getBounds())

if (!isCompleted && centrifugoToken) {

  const protocol = window.location.protocol === 'https:' ? 'wss': 'ws'

  const centrifuge = new Centrifuge(`${protocol}://${window.location.hostname}/centrifugo/connection/websocket`)
  centrifuge.setToken(centrifugoToken)

  centrifuge.subscribe(centrifugoChannel, function(message) {
    const latLng = [
      message.data.coords.lat,
      message.data.coords.lng
    ]

    if (!courierMarker) {
      courierMarker = L.marker(latLng, { icon: createIcon(message.data.user) })
      courierMarker.setOpacity(1)
      courierMarker.addTo(map)

      group.addLayer(courierMarker)
      map.fitBounds(group.getBounds())
    }

    courierMarker.setLatLng(latLng).update()
  })

  centrifuge.connect()
}

if (isCompleted) {
  document.querySelector('.delivery__tracking__map-container').classList.add('delivery__tracking__map-container--completed')
}
