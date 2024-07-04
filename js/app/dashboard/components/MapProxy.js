import _ from 'lodash'
import L from 'leaflet'
import 'leaflet.markercluster'
import 'leaflet-area-select'
import 'leaflet-swoopy'
import React, { StrictMode } from 'react'

import MapHelper, { mapColorHash } from '../../MapHelper'
import LeafletPopupContent from './LeafletPopupContent'
import CourierPopupContent from './CourierPopupContent'
import { createLeafletIcon } from '../../components/Avatar'
import { isMarkerInsidePolygon } from '../utils'
import { render } from 'react-dom'
import { createRoot } from 'react-dom/client'

const tagsColor = tags => {
  const tag = _.first(tags)

  return tag.color
}

const taskColor = (task, selected, useAvatarColors, polylineEnabled = {}, tourPolylinesEnabled = {}, taskIdToTourIdMap, tourIdToColorMap) => {

  if (selected) {
    return '#EEB516'
  } else if (taskIdToTourIdMap.get(task['@id']) && tourPolylinesEnabled[taskIdToTourIdMap.get(task['@id'])]) {
    return tourIdToColorMap.get(taskIdToTourIdMap.get(task['@id']))
  } else if (task.isAssigned && (useAvatarColors || polylineEnabled[task.assignedTo])) {
    return mapColorHash.hex(task.assignedTo)
  } else if (task.group && task.group.tags.length > 0) {
    return tagsColor(task.group.tags)
  } else if (task.tags.length > 0) {
    return tagsColor(task.tags)
  } else {
    return '#777'
  }
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

export default class MapProxy {

  constructor(map, options) {
    this.map = map
    this.polylineLayerGroups = new Map()
    this.polylineAsTheCrowFliesLayerGroups = new Map()

    this.taskMarkers = new Map()
    this.taskPopups = new Map()
    this.taskConnectCircles = new Map()

    this.pickupGroupMarkers = new Map()
    this.pickupGroupPopups = new Map()
    this.pickupGroupIcons = new Map()

    this.courierMarkers = new Map()
    this.courierPopups = new Map()
    this.courierLayerGroup = new L.LayerGroup()
    this.courierLayerGroup.addTo(this.map)

    this.drawPolylineLayerGroup = new L.LayerGroup()
    this.drawPolylineLayerGroup.addTo(this.map)

    this.onEditClick = options.onEditClick
    this.selectTaskOnMarkerClick = options.selectTaskOnMarkerClick
    this.toggleTaskOnMarkerClick = options.toggleTaskOnMarkerClick

    this.tasksLayerGroup = new L.LayerGroup()
    this.tasksLayerGroup.addTo(this.map)

    this.swoopyLayerGroup = L.layerGroup()
    this.swoopyLayerGroup.addTo(this.map)

    this.clusterGroup = L.markerClusterGroup({
      showCoverageOnHover: false,
    })

    this.pickupClusterGroup = L.markerClusterGroup({
      showCoverageOnHover: false,
      spiderfyOnMaxZoom: false,
      zoomToBoundsOnClick: false,
      maxClusterRadius: (zoom) => {
        if (zoom >= 14) {

          return 0
        }

        return 50
      },
    })
    this.pickupClusterGroup.on('clusterclick', (a) => {
      L.popup({
        offset: [ 0, -15 ],
        className: 'leaflet-popup-pickup-group'
      })
        .setLatLng(a.latlng)
        .setContent(options.onPickupClusterClick(a))
        .openOn(this.map)
    })
    this.pickupClusterGroup.addTo(this.map)

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

    this.map.on('pm:create', ({layer}) => {

      const polygonLayer = layer
      L.Util.requestAnimFrame(() => {
        const markers = []
        this.map.eachLayer((marker) => {
          if (!_.includes(Array.from(this.taskMarkers.values()), marker)) {
            return
          }
          if (isMarkerInsidePolygon(marker, polygonLayer)) {
            markers.push(marker)
          }
        })
        options.onMarkersSelected(markers)
        map.removeLayer(polygonLayer)
        map.pm.disableDraw()
      })
    })
  }

  addTask(task, useAvatarColors, selected = false, isRestaurantAddress = false, polylineEnabled = {}, tourPolylinesEnabled = {}, taskIdToTourIdMap, tourIdToColorMap) {

    let marker = this.taskMarkers.get(task['@id'])

    const color = taskColor(task, selected, useAvatarColors, polylineEnabled, tourPolylinesEnabled, taskIdToTourIdMap, tourIdToColorMap)
    const iconName = taskIcon(task)
    const coords = [task.address.geo.latitude, task.address.geo.longitude]
    const latLng = L.latLng(task.address.geo.latitude, task.address.geo.longitude)

    let popupComponent

    if (!marker) {

      marker = MapHelper.createMarker(coords, iconName, 'marker', color)

      const el = document.createElement('div')
      const root = createRoot(el)

      this.taskMarkers.set(task['@id'], marker)

      marker.bindPopup(() => {
        popupComponent = React.createRef()
        root.render(
          <StrictMode>
            <LeafletPopupContent
              ref={ popupComponent }
              task={ task }
              onEditClick={ this.onEditClick }
            />
          </StrictMode>
          )
        this.taskPopups.set(task['@id'], popupComponent)
        return el
      }).addTo(this.map)

      marker.on('click', (e) => {
        if(e.originalEvent.ctrlKey) { // e is a leaflet 'click' event
          this.toggleTaskOnMarkerClick(task)
        } else {
          this.selectTaskOnMarkerClick(task['@id'])
        }
      })

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
    }

    popupComponent = this.taskPopups.get(task['@id'])

    if (popupComponent) {
      popupComponent.current.updateTask(task)
    }

    L.Util.setOptions(marker, { task })

    marker.off('mouseover').on('mouseover', () => this.onTaskMouseOver(task))
    marker.off('mouseout').on('mouseout', () => this.onTaskMouseOut(task))
    marker.off('mousedown').on('mousedown', e => {
      // Make sure the element is not dragged
      // @see https://javascript.info/mouse-drag-and-drop
      e.originalEvent.target.ondragstart = () => false
      this.onTaskMouseDown(task)
    })

    if (task.type === 'PICKUP' && isRestaurantAddress) {
      this.pickupClusterGroup.addLayer(marker)
    } else {
      this.tasksLayerGroup.addLayer(marker)
      this.clusterGroup.addLayer(marker)
    }
  }

  enableConnect(task, active = false) {
    let circle = this.taskConnectCircles.get(task['@id'])
    if (!circle) {
      // Use CircleMarker to keep size independent of zoom level
      // @see https://stackoverflow.com/a/24335153
      circle = L.circleMarker(this.toLatLng(task), { radius: 4, opacity: 1.0, fillOpacity: 1.0 })
      this.taskConnectCircles.set(task['@id'], circle)
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
    let circle = this.taskConnectCircles.get(task['@id'])
    if (circle && this.map.hasLayer(circle)) {
      circle.removeFrom(this.map)
    }
  }

  hideTask(task) {
    const marker = this.taskMarkers.get(task['@id'])
    if (marker) {
      this.tasksLayerGroup.removeLayer(marker)
      this.clusterGroup.removeLayer(marker)
      this.pickupClusterGroup.removeLayer(marker)
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

  setPolylineAsTheCrowFlies(username, polyline, color) {

    const layerGroup = this.getPolylineAsTheCrowFliesLayerGroup(username)
    layerGroup.clearLayers()

    layerGroup.addLayer(
      MapHelper.createPolylineWithArrows(polyline, color)
    )
  }

  setPolyline(username, polyline, color) {

    const layerGroup = this.getPolylineLayerGroup(username)
    layerGroup.clearLayers()

    layerGroup.addLayer(
      MapHelper.createPolylineWithArrows(polyline, color)
    )
  }

  showPolyline(username, style = 'normal') {
    if (style === 'as_the_crow_flies') {
      this.getPolylineLayerGroup(username).removeFrom(this.map)
      this.getPolylineAsTheCrowFliesLayerGroup(username).addTo(this.map)
    } else {
      this.getPolylineAsTheCrowFliesLayerGroup(username).removeFrom(this.map)
      this.getPolylineLayerGroup(username).addTo(this.map)
    }
  }

  hidePolyline(username) {
    this.getPolylineLayerGroup(username).removeFrom(this.map)
    this.getPolylineAsTheCrowFliesLayerGroup(username).removeFrom(this.map)
  }

  setGeolocation(username, position, lastSeen, offline) {

    let marker = this.courierMarkers.get(username)
    let popupComponent = this.courierPopups.get(username)

    if (!marker) {

      marker = L.marker(position, { icon: createLeafletIcon(username), lastSeen })
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

      const tooltip = L.tooltip({
        offset: [ 0, -15 ],
        direction: 'top'
      }).setContent(popupContent)

      marker.bindTooltip(tooltip)
      marker
        .on('tooltipopen', () => {
          this.showPolyline(username, 'as_the_crow_flies')
        })
        .on('tooltipclose', () => {
          this.hidePolyline(username)
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

  pointToNext(task, clusterLatLng) {

    if (!task.next) {
      return
    }

    const thisMarker = this.taskMarkers.get(task['@id'])
    const nextMarker = this.taskMarkers.get(task.next)

    if (!thisMarker || !nextMarker) {
      return
    }

    this.swoopyLayerGroup.clearLayers()

    const swoopy = L.swoopyArrow(clusterLatLng, nextMarker.getLatLng(), {
      color: '#3498DB',
      weight: 3,
      arrowId: '#custom_arrow',
      opacity: 0.9,
      factor: 0.7,
    })

    swoopy.addTo(this.swoopyLayerGroup)
  }

  hideNext() {
    this.swoopyLayerGroup.clearLayers()
  }
}
