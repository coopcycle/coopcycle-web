import React, { Component } from 'react'
import { findDOMNode } from 'react-dom'
import { connect } from 'react-redux'
import MapHelper from '../../MapHelper'
import MapProxy from './MapProxy'
import _ from 'lodash'

class LeafletMap extends Component {

  componentDidMount() {

    this.map = MapHelper.init('map')
    this.proxy = new MapProxy(this.map)

    this.props.tasks.forEach(task => this.proxy.addTask(task))
    _.forEach(this.props.polylines, (polyline, username) => this.proxy.setPolyline(username, polyline))

    const couriersMap = new Map()
    const couriersLayer = new L.LayerGroup()

    couriersLayer.addTo(this.map)

    const { socket } = this.props

    socket.on('tracking', data => {
      let marker
      if (!couriersMap.has(data.user)) {
        marker = MapHelper.createMarker(data.coords, 'bicycle', 'circle', '#000')
        const popupContent = `<div class="text-center">${data.user}</div>`
        marker.bindPopup(popupContent, {
          offset: [3, 70]
        })
        couriersLayer.addLayer(marker)
        couriersMap.set(data.user, marker)
      } else {
        marker = couriersMap.get(data.user)
        marker.setLatLng(data.coords).update()
        marker.setIcon(MapHelper.createMarkerIcon('bicycle', 'circle', '#000'))
      }
    })

    socket.on('online', username => {
      console.log(`User ${username} is connected`)
    })

    socket.on('offline', username => {
      if (!couriersMap.has(username)) {
        console.error(`User ${username} not found`)
        return
      }
      const marker = couriersMap.get(username)
      marker.setIcon(MapHelper.createMarkerIcon('bicycle', 'circle', '#CCC'))
    })

  }

  componentDidUpdate(prevProps, prevState) {
    const { polylineEnabled, polylines } = this.props

    _.forEach(polylines, (polyline, username) => this.proxy.setPolyline(username, polyline))
    _.forEach(polylineEnabled, (enabled, username) => {
      if (enabled) {
        this.proxy.showPolyline(username, polylines[username])
      } else {
        this.proxy.hidePolyline(username)
      }
    })
  }

  render() {
    return (
      <div id="map"></div>
    )
  }
}

function mapStateToProps(state, ownProps) {

  const { assignedTasksByUser, unassignedTasks, polylineEnabled } = state

  const tasks = unassignedTasks.slice()
  _.forEach(assignedTasksByUser, (userTasks, username) => userTasks.forEach(task => tasks.push(task)))

  let polylines = {}
  _.forEach(assignedTasksByUser, (userTasks, username) => {
    polylines[username] = userTasks.polyline
  })

  return {
    tasks,
    polylines,
    polylineEnabled
  }
}

export default connect(mapStateToProps)(LeafletMap)
