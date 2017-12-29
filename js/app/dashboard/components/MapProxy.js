import _ from 'lodash'
import L from 'leaflet'
import MapHelper from '../../MapHelper'

const polylineOptions = {
  color: '#3498DB',
  opacity: 0.7
}

export default class MapProxy {

  constructor(map, options) {
    this.map = map
    this.layers = new Map()
    this.polylineCache = new Map()
    this.markersLayers = new Map()
    this.options = options
    options.users.forEach(user => this.addUser(user))
  }

  addUser(user) {
    this.layers.set(user.username, {
      markers: L.layerGroup([]),
      route: L.layerGroup([]),
      polyline: L.layerGroup([])
    })
  }

  zoom(username) {
    const layers = this.layers.get(username)
    const group = new L.featureGroup()
    if (layers.markers.getLayers().length === 0) {
      return
    }
    layers.markers.eachLayer(layer => group.addLayer(layer))
    layers.polyline.eachLayer(layer => group.addLayer(layer))
    this.map.fitBounds(group.getBounds().pad(0.5))
  }

  route(markers) {
    return MapHelper.route(markers.map(marker => [marker.address.geo.latitude, marker.address.geo.longitude]))
  }

  clearPolyline(username) {
    const layers = this.layers.get(username)
    layers.polyline.clearLayers()
  }

  refreshRoute(username, route) {
    const latLngs = MapHelper.decodePolyline(route.geometry)
    const polyline = L.polyline(latLngs, polylineOptions)
    const layers = this.layers.get(username)
    layers.polyline.clearLayers()
    layers.polyline.addLayer(polyline)
  }

  addMarker(username, coords, type) {

    const color = type === 'pickup' ? '#337ab7' : '#27AE60'
    const icon = type === 'pickup' ? 'arrow-up' : 'arrow-down'

    const marker = MapHelper.createMarker(coords, icon, 'marker', color)
    const layers = this.layers.get(username)
    layers.markers.addLayer(marker)

    return marker
  }

  addMarkers(username, delivery) {
    const pickupMarker = MapHelper.createMarker([
      delivery.originAddress.geo.latitude,
      delivery.originAddress.geo.longitude
    ], 'arrow-up', 'marker', '#337ab7')
    const dropoffMarker = MapHelper.createMarker([
      delivery.deliveryAddress.geo.latitude,
      delivery.deliveryAddress.geo.longitude
    ], 'arrow-down', 'marker', '#27AE60')

    const layers = this.layers.get(username)
    layers.markers.addLayer(pickupMarker)
    layers.markers.addLayer(dropoffMarker)

    this.markersLayers.set(username + ':' + delivery['@id'], {
      pickup: pickupMarker,
      dropoff: dropoffMarker
    })
  }

  removeMarkers(username, delivery) {
    const layers = this.layers.get(username)
    const markersLayers = this.markersLayers.get(username + ':' + delivery['@id'])

    layers.markers.removeLayer(markersLayers.pickup)
    layers.markers.removeLayer(markersLayers.dropoff)
  }

  showLayers(username) {
    const layers = this.layers.get(username)
    layers.markers.addTo(this.map)
    layers.route.addTo(this.map)
    layers.polyline.addTo(this.map)
  }

  hideLayers(username) {
    const layers = this.layers.get(username)
    this.map.removeLayer(layers.markers);
    this.map.removeLayer(layers.route)
    this.map.removeLayer(layers.polyline)
  }

}
