import React, { Component } from 'react'
import { connect } from 'react-redux'
import MapHelper from '../../MapHelper'
import MapProxy from './MapProxy'
import _ from 'lodash'
import { setCurrentTask, assignAfter, selectTask, selectTasks as selectTasksAction } from '../redux/actions'
import { selectFilteredTasks } from '../redux/selectors'
import { selectAllTasks, selectTaskLists, selectSelectedDate } from '../../coopcycle-frontend-js/logistics/redux'

class LeafletMap extends Component {

  _draw() {
    const {
      polylines,
      asTheCrowFlies,
      tasks,
      tasksFiltered,
      clustersEnabled,
    } = this.props

    const tasksHidden = _.differenceWith(tasks, tasksFiltered, (a, b) => a['@id'] === b['@id'])

    tasksFiltered.forEach(task => this.proxy.addTask(task))
    tasksHidden.forEach(task => this.proxy.hideTask(task))

    _.forEach(polylines, (polyline, username) => this.proxy.setPolyline(username, polyline))
    _.forEach(asTheCrowFlies, (polyline, username) => this.proxy.setPolylineAsTheCrowFlies(username, polyline))

    if (clustersEnabled) {
      this.proxy.showClusters()
    } else {
      this.proxy.hideClusters()
    }
  }

  componentDidMount() {

    this.map = MapHelper.init('map', {
      onLoad: this.props.onLoad
    })
    this.proxy = new MapProxy(this.map, {
      onEditClick: this.props.setCurrentTask,
      onTaskMouseDown: task => {
        if (task.isAssigned) {
          this.proxy.disableDragging()
          this.fromTask = task
        }
      },
      onTaskMouseOver: task => {
        if (task.isAssigned) {
          this.proxy.enableConnect(task)
        }
        if (this.fromTask && task !== this.fromTask && !task.isAssigned) {
          this.toTask = task
          this.proxy.enableConnect(task, true)
        }
      },
      onTaskMouseOut: (task) => {
        this.toTask = null
        this.proxy.disableConnect(task)
      },
      onMouseMove: (e) => {
        if (this.fromTask) {
          const targetLatLng = !!this.toTask ? this.proxy.toLatLng(this.toTask) : e.latlng
          this.proxy.setDrawPolyline(this.proxy.toLatLng(this.fromTask), targetLatLng, !!this.toTask)
          this.proxy.enableConnect(this.fromTask, !!this.toTask)
        }
      },
      onMouseUp: () => {

        if (!!this.fromTask && !!this.toTask) {
          this.props.assignAfter(this.fromTask.assignedTo, this.toTask, this.fromTask)
        }

        if (!!this.fromTask) {
          this.proxy.disableConnect(this.fromTask)
        }
        if (!!this.toTask) {
          this.proxy.disableConnect(this.toTask)
        }

        this.fromTask = null
        this.toTask = null

        this.proxy.clearDrawPolyline()
        this.proxy.enableDragging()
      },
      onMarkersSelected: markers => {
        const tasks = []
        markers.forEach(marker => {
          const task = _.find(this.props.tasks, t => t['@id'] === marker.options.task)
          if (task) {
            tasks.push(task)
          }
        })
        this.props.selectTasks(tasks)
      }
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
      polylineStyle,
    } = this.props

    this._draw()

    selectedTasks.forEach(task => this.proxy.addTask(task, '#EEB516'))

    _.forEach(polylineEnabled, (enabled, username) => {
      if (enabled) {
        if (polylineStyle === 'as_the_crow_flies') {
          this.proxy.hidePolyline(username)
          this.proxy.showPolylineAsTheCrowFlies(username, polylines[username])
        } else {
          this.proxy.hidePolylineAsTheCrowFlies(username)
          this.proxy.showPolyline(username, polylines[username])
        }
      } else {
        this.proxy.hidePolylineAsTheCrowFlies(username)
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

  const { polylineEnabled } = state

  const taskLists = selectTaskLists(state)

  let polylines = {}
  _.forEach(taskLists, taskList => {
    polylines[taskList.username] = taskList.polyline
  })

  let asTheCrowFlies = {}
  _.forEach(taskLists, taskList => {
    asTheCrowFlies[taskList.username] =
      _.map(taskList.items, item => ([ item.address.geo.latitude, item.address.geo.longitude ]))
  })

  const tasks = selectAllTasks(state)

  return {
    tasks,
    tasksFiltered: selectFilteredTasks({
      tasks,
      filters: state.filters,
      date: selectSelectedDate(state)
    }),
    polylines,
    polylineEnabled,
    selectedTasks: state.selectedTasks,
    positions: state.positions,
    offline: state.offline,
    polylineStyle: state.polylineStyle,
    asTheCrowFlies,
    clustersEnabled: state.clustersEnabled,
  }
}

function mapDispatchToProps (dispatch) {
  return {
    setCurrentTask: task => dispatch(setCurrentTask(task)),
    assignAfter: (username, task, after) => dispatch(assignAfter(username, task, after)),
    selectTask: task => dispatch(selectTask(task)),
    selectTasks: tasks => dispatch(selectTasksAction(tasks)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(LeafletMap)
