import _ from 'lodash'
import L from 'leaflet'
import MapHelper from '../../MapHelper'
import moment from 'moment'

moment.locale($('html').attr('lang'))

const polylineOptions = {
  color: '#3498DB',
  opacity: 0.7
}

export default class MapProxy {

  constructor(map) {
    this.map = map
    this.polylineLayerGroups = new Map()

    this.courierMarkers = new Map()
    this.courierLayerGroup = new L.LayerGroup()
    this.courierLayerGroup.addTo(this.map)
  }

  addTask(task) {
    const color  = task.type === 'PICKUP' ? '#337ab7' : '#27AE60'
    const icon   = task.type === 'PICKUP' ? 'cube' : 'arrow-down'
    const coords = [task.address.geo.latitude, task.address.geo.longitude]
    const marker = MapHelper.createMarker(coords, icon, 'marker', color)

    const doneAfter = moment(task.doneAfter).format('LT')
    const doneBefore = moment(task.doneBefore).format('LT')

    const popupContent = `
      <strong>${task.address.streetAddress}</strong>
      <br>
      <span>${doneAfter} - ${doneBefore}</span>
    `

    const popup = L.popup()
      .setContent(popupContent)

    marker.bindPopup(popup)

    marker.addTo(this.map)
  }

  getPolylineLayerGroup(username) {
    let layerGroup = this.polylineLayerGroups.get(username)

    if (!layerGroup) {
      layerGroup = L.layerGroup()
      this.polylineLayerGroups.set(username, layerGroup)
    }

    return layerGroup
  }

  setPolyline(username, polyline) {
    const layer = L.polyline(MapHelper.decodePolyline(polyline), polylineOptions)
    const layerGroup = this.getPolylineLayerGroup(username)

    layerGroup.clearLayers()
    layerGroup.addLayer(layer)
  }

  showPolyline(username) {
    this.getPolylineLayerGroup(username).addTo(this.map)
  }

  hidePolyline(username) {
    this.getPolylineLayerGroup(username).removeFrom(this.map)
  }

  setOnline(username) {
    console.log(`User ${username} is online`)
    if (!this.courierMarkers.has(username)) {
      return
    }
    const marker = this.courierMarkers.get(username)
    marker.setIcon(MapHelper.createMarkerIcon('bicycle', 'circle', '#000'))
  }

  setOffline(username) {
    console.log(`User ${username} is offline`)
    if (!this.courierMarkers.has(username)) {
      return
    }
    const marker = this.courierMarkers.get(username)
    marker.setIcon(MapHelper.createMarkerIcon('bicycle', 'circle', '#CCC'))
  }

  setGeolocation(username, position) {
    let marker = this.courierMarkers.get(username)
    if (!marker) {
      marker = MapHelper.createMarker(position, 'bicycle', 'circle', '#000')
      const popupContent = `<div class="text-center">${username}</div>`
      marker.bindPopup(popupContent, {
        offset: [3, 70]
      })
      this.courierLayerGroup.addLayer(marker)
      this.courierMarkers.set(username, marker)
    } else {
      marker.setLatLng(position).update()
    }
  }
}
