import _ from 'lodash'
import L from 'leaflet'
import MapHelper from '../../MapHelper'
import moment from 'moment'

moment.locale($('html').attr('lang'))

const DEFAULT_PICKUP_COLOR = '#337ab7'
const DEFAULT_DROPOFF_COLOR = '#27AE60'

const tagsColor = tags => {
  const tag = _.first(tags)

  return tag.color
}

const taskColor = task => {

  if (task.group && task.group.tags.length > 0) {
    return tagsColor(task.group.tags)
  }

  if (task.tags.length > 0) {
    const tag = _.first(task.tags)
    return tag.color
  }

  return '#777'
}

const taskIcon = task => {

  if (task.status === 'TODO') {
    if (task.type === 'PICKUP') {
      return 'cube'
    }
    if (task.type === 'DROPOFF') {
      return 'arrow-down'
    }
  } else {
    if (task.status === 'DONE') {
      return 'check'
    }
    if (task.status === 'FAILED') {
      return 'remove'
    }
  }

  return 'question'
}

const polylineOptions = {
  color: '#3498DB',
  opacity: 0.7
}

const createIcon = username => {
  const iconUrl = window.AppData.Dashboard.avatarURL.replace('__USERNAME__', username)

  return L.icon({
    iconUrl: iconUrl,
    iconSize:    [20, 20], // size of the icon
    iconAnchor:  [10, 10], // point of the icon which will correspond to marker's location
    popupAnchor: [-2, -72], // point from which the popup should open relative to the iconAnchor,
  });
}

export default class MapProxy {

  constructor(map) {
    this.map = map
    this.polylineLayerGroups = new Map()

    this.taskMarkers = new Map()

    this.courierMarkers = new Map()
    this.courierLayerGroup = new L.LayerGroup()
    this.courierLayerGroup.addTo(this.map)
  }

  addTask(task, markerColor) {
    let marker = this.taskMarkers.get(task['id'])

    if (!marker) {
      const color = markerColor || taskColor(task),
        icon = taskIcon(task),
        coords = [task.address.geo.latitude, task.address.geo.longitude],
        assignedTo = task.assignedTo ? ' assignée à ' + task.assignedTo : '',
        doneAfter = moment(task.doneAfter).format('LT'),
        doneBefore = moment(task.doneBefore).format('LT'),
        taskId = task['id']

      marker = MapHelper.createMarker(coords, icon, 'marker', color)

      const popupContent = `
        <span>
            Tâche #${taskId} ${ task.address.name ? ' - ' + task.address.name : '' }
            ${ assignedTo }

        </span>
        <br>
        <span>${task.address.streetAddress} de ${doneAfter} à ${doneBefore}</span>
        <br>
        ${ task.tags.map((item) => '<span style="color:#fff; padding: 2px; background-color:' + item.color + ';">' +item.name + '</span>').join(' ') }
        <span>${ task.comments ? 'Commentaires : ' + task.comments : '' }</span>
      `

      const popup = L.popup()
        .setContent(popupContent)

      marker.bindPopup(popup)
      this.taskMarkers.set(task['id'], marker)
    }

    marker.addTo(this.map)
  }

  hideTask(task) {
    const marker = this.taskMarkers.get(task['id'])
    if (marker) {
      this.map.removeLayer(marker)
    }
  }

  removeTask(task) {
    this.taskMarkers.delete(task['id'])
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
    if (!this.courierMarkers.has(username)) {
      return
    }
    const marker = this.courierMarkers.get(username)
    marker.setIcon(createIcon(username))
    marker.setOpacity(1)
  }

  setOffline(username) {
    console.log(`User ${username} is offline`)
    if (!this.courierMarkers.has(username)) {
      return
    }
    const marker = this.courierMarkers.get(username)
    marker.setIcon(createIcon(username))
    marker.setOpacity(0.5)
  }

  setGeolocation(username, position) {
    let marker = this.courierMarkers.get(username)
    if (!marker) {

      marker = L.marker(position, { icon: createIcon(username) })
      marker.setOpacity(1)

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
