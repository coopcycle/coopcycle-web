import React, { Component } from 'react'
import { connect } from 'react-redux'
import MapHelper from '../../MapHelper'
import MapProxy from './MapProxy'
import _ from 'lodash'
import { setCurrentTask } from '../store/actions'
import { selectTasks, selectFilteredTasks } from '../store/selectors'

class LeafletMap extends Component {

  _draw() {
    const {
      polylines,
      tasks,
      tasksFiltered,
    } = this.props

    const tasksHidden = _.differenceWith(tasks, tasksFiltered, (a, b) => a['@id'] === b['@id'])

    tasksFiltered.forEach(task => this.proxy.addTask(task))
    tasksHidden.forEach(task => this.proxy.hideTask(task))

    _.forEach(polylines, (polyline, username) => this.proxy.setPolyline(username, polyline))
  }

  componentDidMount() {

    this.map = MapHelper.init('map', {
      onLoad: this.props.onLoad
    })
    this.proxy = new MapProxy(this.map, {
      onEditClick: this.props.setCurrentTask
    })

    this._draw()
  }

  componentDidUpdate(prevProps) {

    const {
      polylineEnabled,
      polylines,
      selectedTasks,
      positions,
      offline,
    } = this.props

    this._draw()

    selectedTasks.forEach(task => this.proxy.addTask(task, '#EEB516'))

    _.forEach(polylineEnabled, (enabled, username) => {
      if (enabled) {
        this.proxy.showPolyline(username, polylines[username])
      } else {
        this.proxy.hidePolyline(username)
      }
    })

    if (prevProps.positions !== positions) {
      positions.forEach(position => {
        const { username, coords, lastSeen } = position
        this.proxy.setGeolocation(username, coords, lastSeen)
        this.proxy.setOnline(username)
      })
    }

    if (prevProps.offline !== offline) {
      offline.forEach(username => {
        this.proxy.setOffline(username)
      })
    }

  }

  render() {
    return (
      <div id="map"></div>
    )
  }
}

function mapStateToProps(state) {

  const { taskLists, polylineEnabled } = state

  let polylines = {}
  _.forEach(taskLists, taskList => {
    polylines[taskList.username] = taskList.polyline
  })

  return {
    tasks: selectTasks(state),
    tasksFiltered: selectFilteredTasks(state),
    polylines,
    polylineEnabled,
    selectedTasks: state.selectedTasks,
    positions: state.positions,
    offline: state.offline
  }
}

function mapDispatchToProps (dispatch) {
  return {
    setCurrentTask: task => dispatch(setCurrentTask(task))
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(LeafletMap)
