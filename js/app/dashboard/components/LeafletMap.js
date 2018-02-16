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

    _.forEach(this.props.tasks, task => this.proxy.addTask(task))
    _.forEach(this.props.polylines, (polyline, username) => this.proxy.setPolyline(username, polyline))

    const { socket } = this.props

    socket.on('tracking', data => {
      const { user, coords } = data
      this.proxy.setGeolocation(user, coords)
      this.proxy.setOnline(user)
    })
    socket.on('online', username => this.proxy.setOnline(username))
    socket.on('offline', username => this.proxy.setOffline(username))
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
