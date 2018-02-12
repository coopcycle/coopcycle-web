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
    _.forEach(this.props.taskLists, (taskList, username) => this.proxy.addTaskList(username, taskList))

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
    const { tasks } = this.props
    _.forEach(this.props.taskLists, (taskList, username) => this.proxy.addTaskList(username, taskList))
  }

  render() {
    return (
      <div id="map"></div>
    )
  }
}

function mapStateToProps(state, ownProps) {

  const { assignedTasksByUser, unassignedTasks } = state

  const tasks = unassignedTasks.slice()
  _.forEach(assignedTasksByUser, (userTasks, username) => userTasks.forEach(task => tasks.push(task)))

  let taskLists = {}
  _.forEach(assignedTasksByUser, (userTasks, username) => {
    taskLists[username] = {
      polyline: userTasks.polyline
    }
  })

  return {
    tasks,
    taskLists
  }
}

export default connect(mapStateToProps)(LeafletMap)
