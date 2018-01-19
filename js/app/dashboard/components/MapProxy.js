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
    this.layers = new Map()
  }

  addTask(task) {
    const color  = task.type === 'PICKUP' ? '#337ab7' : '#27AE60'
    const icon   = task.type === 'PICKUP' ? 'arrow-up' : 'arrow-down'
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

  addTaskList(username, taskList) {

    let layers = this.layers.get(username)

    if (!layers) {
      layers = {
        polyline: L.layerGroup(),
        tasks: L.layerGroup(),
      }
      this.layers.set(username, layers)

      layers.polyline.addTo(this.map)
    }

    const polyline = L.polyline(MapHelper.decodePolyline(taskList.polyline), polylineOptions)
    layers.polyline.clearLayers()
    layers.polyline.addLayer(polyline)

  }
}
