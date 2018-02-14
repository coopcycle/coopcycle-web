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
}
