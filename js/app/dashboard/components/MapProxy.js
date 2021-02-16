import _ from 'lodash'
import L from 'leaflet'
import 'leaflet-polylinedecorator'
import 'leaflet.markercluster'
import 'leaflet-area-select'
import React from 'react'
import { render } from 'react-dom'
import ColorHash from 'color-hash'

import MapHelper from '../../MapHelper'
import LeafletPopupContent from './LeafletPopupContent'
import CourierPopupContent from './CourierPopupContent'

const tagsColor = tags => {
  const tag = _.first(tags)

  return tag.color
}

const taskColor = task => {

  if (task.group && task.group.tags.length > 0) {
    return tagsColor(task.group.tags)
  }

  if (task.tags.length > 0) {
    return tagsColor(task.tags)
  }

  return task.isAssigned ? colorHash.hex(task.assignedTo) : '#777'
}

const taskIcon = task => {

  switch (task.status) {
  case 'TODO':
    if (task.type === 'PICKUP') {
      return 'cube'
    }
    if (task.type === 'DROPOFF') {
      return 'arrow-down'
    }
    break
  case 'DOING':
    return 'play'
  case 'DONE':
    return 'check'
  case 'FAILED':
    return 'remove'
  case 'CANCELLED':
    return 'ban'
  }

  return 'question'
}

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

const colorHash = new ColorHash()

export default class MapProxy {

  constructor(map, options) {
    this.map = map
    this.polylineLayerGroups = new Map()
    this.polylineAsTheCrowFliesLayerGroups = new Map()

    this.taskMarkers = new Map()
    this.taskPopups = new Map()
    this.taskConnectCircles = new Map()

    this.courierMarkers = new Map()
    this.courierPopups = new Map()
    this.courierLayerGroup = new L.LayerGroup()
    this.courierLayerGroup.addTo(this.map)

    this.drawPolylineLayerGroup = new L.LayerGroup()
    this.drawPolylineLayerGroup.addTo(this.map)

    this.onEditClick = options.onEditClick

    this.tasksLayerGroup = new L.LayerGroup()
    this.tasksLayerGroup.addTo(this.map)

    this.clusterGroup = L.markerClusterGroup({
      showCoverageOnHover: false,
    })

    this.onTaskMouseDown = options.onTaskMouseDown
    this.onTaskMouseOver = options.onTaskMouseOver
    this.onTaskMouseOut = options.onTaskMouseOut

    this.map.on('mousemove', e => {
      options.onMouseMove(e)
    })
    this.map.on('mouseup', e => {
      options.onMouseUp(e)
    })

    this.map.selectArea.enable()

    this.map.on('areaselected', (e) => {
      L.Util.requestAnimFrame(() => {
        const markers = []
        this.map.eachLayer((layer) => {
          if (!_.includes(Array.from(this.taskMarkers.values()), layer)) {
            return
          }
          if (!e.bounds.contains(layer.getLatLng())) {
            return
          }
          markers.push(layer)
        })
        options.onMarkersSelected(markers)
      })
    })

  }

  addTask(task, markerColor) {

    let marker = this.taskMarkers.get(task['id'])

    const color = markerColor || taskColor(task)
    const iconName = taskIcon(task)
    const coords = [task.address.geo.latitude, task.address.geo.longitude]
    const latLng = L.latLng(task.address.geo.latitude, task.address.geo.longitude)

    let popupComponent = this.taskPopups.get(task['id'])

    if (!marker) {

      marker = MapHelper.createMarker(coords, iconName, 'marker', color)

      const el = document.createElement('div')

      popupComponent = React.createRef()

      const cb = () => {
        this.taskMarkers.set(task['id'], marker)
        this.taskPopups.set(task['id'], popupComponent)
      }

      render(<LeafletPopupContent
        task={ task }
        ref={ popupComponent }
        onEditClick={ () => this.onEditClick(task) } />, el, cb)

      const popup = L.popup()
        .setContent(el)

      marker.bindPopup(popup)

      marker.options.task = task['@id']

    } else {

      // OPTIMIZATION
      // Do *NOT* recreate an icon each time, it's expensive

      const newOpts = {
        icon: iconName,
        textColor: color,
        borderColor: color,
      }
      const currentOpts = _.pick(marker.options.icon.options, [
        'icon',
        'textColor',
        'borderColor',
      ])
      if (!_.isEqual(currentOpts, newOpts)) {
        L.Util.setOptions(marker.options.icon, newOpts)
        marker.setIcon(marker.options.icon)
      }

      if (!marker.getLatLng().equals(latLng)) {
        marker.setLatLng(latLng).update()
      }

      popupComponent.current.updateTask(task)

    }

    marker.off('mouseover').on('mouseover', () => this.onTaskMouseOver(task))
    marker.off('mouseout').on('mouseout', () => this.onTaskMouseOut(task))
    marker.off('mousedown').on('mousedown', e => {
      // Make sure the element is not dragged
      // @see https://javascript.info/mouse-drag-and-drop
      e.originalEvent.target.ondragstart = () => false
      this.onTaskMouseDown(task)
    })

    this.tasksLayerGroup.addLayer(marker)
    this.clusterGroup.addLayer(marker)
  }

  enableConnect(task, active = false) {
    let circle = this.taskConnectCircles.get(task['id'])
    if (!circle) {
      // Use CircleMarker to keep size independent of zoom level
      // @see https://stackoverflow.com/a/24335153
      circle = L.circleMarker(this.toLatLng(task), { radius: 4, opacity: 1.0, fillOpacity: 1.0 })
      this.taskConnectCircles.set(task['id'], circle)
    }
    if (active) {
      circle.setStyle({
        color: '#2ECC40',
        fillColor: '#2ECC40'
      })
    } else {
      circle.setStyle({
        color: '#3388ff',
        fillColor: '#3388ff'
      })
    }
    if (this.map.hasLayer(circle)) {
      return
    }
    circle.addTo(this.map)
    circle.bringToFront()
  }

  disableConnect(task) {
    let circle = this.taskConnectCircles.get(task['id'])
    if (circle && this.map.hasLayer(circle)) {
      circle.removeFrom(this.map)
    }
  }

  hideTask(task) {
    const marker = this.taskMarkers.get(task['id'])
    if (marker) {
      this.tasksLayerGroup.removeLayer(marker)
      this.clusterGroup.removeLayer(marker)
    }
  }

  getPolylineLayerGroup(username) {
    let layerGroup = this.polylineLayerGroups.get(username)

    if (!layerGroup) {
      layerGroup = L.layerGroup()
      this.polylineLayerGroups.set(username, layerGroup)
    }

    return layerGroup
  }

  showClusters() {
    this.tasksLayerGroup.removeFrom(this.map)
    this.clusterGroup.addTo(this.map)
  }

  hideClusters() {
    this.clusterGroup.removeFrom(this.map)
    this.tasksLayerGroup.addTo(this.map)
  }

  getPolylineAsTheCrowFliesLayerGroup(username) {
    let layerGroup = this.polylineAsTheCrowFliesLayerGroups.get(username)

    if (!layerGroup) {
      layerGroup = L.layerGroup()
      this.polylineAsTheCrowFliesLayerGroups.set(username, layerGroup)
    }

    return layerGroup
  }

  setPolylineAsTheCrowFlies(username, polyline) {
    const layerGroup = this.getPolylineAsTheCrowFliesLayerGroup(username)
    layerGroup.clearLayers()

    const color = colorHash.hex(username)

    const layer = L.polyline(polyline, {
      ...polylineOptions,
      color,
    })

    // Add arrows to polyline
    const decorator = L.polylineDecorator(layer, {
      patterns: [
        {
          offset: '5%',
          repeat: '12.5%',
          symbol: L.Symbol.arrowHead({
            pixelSize: 12,
            polygon: false,
            pathOptions: {
              stroke: true,
              color,
              opacity: 0.7
            }
          })
        }
      ]
    })

    layerGroup.addLayer(layer)
    layerGroup.addLayer(decorator)
  }

  setPolyline(username, polyline) {

    const layerGroup = this.getPolylineLayerGroup(username)
    layerGroup.clearLayers()

    const color = colorHash.hex(username)

    const layer = L.polyline(MapHelper.decodePolyline(polyline), {
      ...polylineOptions,
      color,
    })

    // Add arrows to polyline
    const decorator = L.polylineDecorator(layer, {
      patterns: [
        {
          offset: '5%',
          repeat: '12.5%',
          symbol: L.Symbol.arrowHead({
            pixelSize: 12,
            polygon: false,
            pathOptions: {
              stroke: true,
              color,
              opacity: 0.7
            }
          })
        }
      ]
    })

    layerGroup.addLayer(layer)
    layerGroup.addLayer(decorator)
  }

  showPolyline(username) {
    this.getPolylineLayerGroup(username).addTo(this.map)
  }

  hidePolyline(username) {
    this.getPolylineLayerGroup(username).removeFrom(this.map)
  }

  showPolylineAsTheCrowFlies(username) {
    this.getPolylineAsTheCrowFliesLayerGroup(username).addTo(this.map)
  }

  hidePolylineAsTheCrowFlies(username) {
    this.getPolylineAsTheCrowFliesLayerGroup(username).removeFrom(this.map)
  }

  setGeolocation(username, position, lastSeen, offline) {

    let marker = this.courierMarkers.get(username)
    let popupComponent = this.courierPopups.get(username)

    if (!marker) {

      marker = L.marker(position, { icon: createIcon(username), lastSeen })
      marker.setOpacity(1)

      popupComponent = React.createRef()
      const popupContent = document.createElement('div')
      const cb = () => {
        this.courierPopups.set(username, popupComponent)
      }

      render(<CourierPopupContent
        ref={ popupComponent }
        username={ username }
        lastSeen={ lastSeen } />, popupContent, cb)

      marker.bindPopup(popupContent, {
        offset: [ 3, 70 ],
        minWidth: 150,
      })

      marker.setOpacity(offline ? 0.5 : 1)

      this.courierLayerGroup.addLayer(marker)
      this.courierMarkers.set(username, marker)

    } else {

      if (!marker.getLatLng().equals(position)) {
        marker.setLatLng(position).update()
      }
      if (marker.options.lastSeen !== lastSeen) {
        popupComponent.current.updateLastSeen(lastSeen)
      }

      marker.setOpacity(offline ? 0.5 : 1)
    }
  }

  enableDragging() {
    this.map.dragging.enable()
  }

  disableDragging() {
    this.map.dragging.disable()
  }

  setDrawPolyline(origin, dest, active = false) {

    let opts = active ? { ...polylineOptions, color: '#2ECC40' } : { ...polylineOptions }

    const layer = L.polyline([ origin, dest ], opts)
    this.drawPolylineLayerGroup.clearLayers()
    this.drawPolylineLayerGroup.addLayer(layer)
  }

  clearDrawPolyline() {
    const layer = L.polyline([], polylineOptions)
    this.drawPolylineLayerGroup.clearLayers()
    this.drawPolylineLayerGroup.addLayer(layer)
  }

  toLatLng(task) {

    return [
      task.address.geo.latitude,
      task.address.geo.longitude
    ]
  }
}
