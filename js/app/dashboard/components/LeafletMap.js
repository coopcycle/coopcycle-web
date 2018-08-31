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
    this.onlineTrackingTimeoutMap = new Map()

    let { tasks, showFinishedTasks, showCancelledTasks } = this.props

    if (!showFinishedTasks) {
      tasks = _.filter(tasks, (task) => { return task.status === 'TODO' })
    }
    if (!showCancelledTasks) {
      tasks = _.filter(tasks, (task) => { return task.status !== 'CANCELLED' })
    }

    _.forEach(tasks, task => this.proxy.addTask(task))
    _.forEach(this.props.polylines, (polyline, username) => this.proxy.setPolyline(username, polyline))

    const { socket } = this.props

    socket.on('tracking', data => {
      const { user, coords } = data
      this.proxy.setGeolocation(user, coords)
      this.proxy.setOnline(user)

      if (this.onlineTrackingTimeoutMap.has(user)) {
        clearTimeout(this.onlineTrackingTimeoutMap[user])
      }

      this.onlineTrackingTimeoutMap[user] = setTimeout(() => { this.proxy.setOffline(user) }, 5 * 60 * 1000)

    })
    socket.on('online', username => this.proxy.setOnline(username))
    socket.on('offline', username => this.proxy.setOffline(username))
  }

  componentDidUpdate(prevProps, prevState) {
    const {
      polylineEnabled,
      polylines,
      tasks,
      selectedTags,
      showUntaggedTasks,
      showFinishedTasks,
      showCancelledTasks,
      selectedTasks
    } = this.props

    _.forEach(polylines, (polyline, username) => this.proxy.setPolyline(username, polyline))
    _.forEach(polylineEnabled, (enabled, username) => {
      if (enabled) {
        this.proxy.showPolyline(username, polylines[username])
      } else {
        this.proxy.hidePolyline(username)
      }
    })

    if (prevProps.showFinishedTasks !== showFinishedTasks) {
      const finishedTasks = _.filter(tasks, task => task.status !== 'TODO')
      if (showFinishedTasks) {
        _.forEach(finishedTasks, task => this.proxy.addTask(task))
      } else {
        _.forEach(finishedTasks, task => this.proxy.hideTask(task))
      }
    }

    if ( prevProps.selectedTags !== selectedTags ) {
      let toHide = _.filter(tasks, (task) => task.tags.length > 0 && _.intersectionBy(task.tags, selectedTags, 'name').length === 0)
      _.forEach(toHide, task => this.proxy.hideTask(task))
      let toShow = _.filter(tasks, (task) => task.tags.length > 0 && _.intersectionBy(task.tags, selectedTags, 'name').length > 0)
      _.forEach(toShow, task => this.proxy.addTask(task))
    }

    if ( prevProps.showUntaggedTasks !== showUntaggedTasks ) {
      let untaggedTasks = _.filter(tasks, (task) => task.tags.length === 0)
      if (showUntaggedTasks) {
        _.forEach(untaggedTasks, task => this.proxy.addTask(task))
      } else {
        _.forEach(untaggedTasks, task => this.proxy.hideTask(task))
      }
    }

    if (prevProps.showCancelledTasks !== showCancelledTasks) {
      const cancelledTasks = _.filter(tasks, task => task.status === 'CANCELLED')
      if (showCancelledTasks) {
        _.forEach(cancelledTasks, task => this.proxy.addTask(task))
      } else {
        _.forEach(cancelledTasks, task => this.proxy.hideTask(task))
      }
    }

    if (prevProps.selectedTasks !== selectedTasks) {
      // TODO Find a better way to do this instead of looping over & over
      prevProps.selectedTasks.forEach(task => {
        this.proxy.removeTask(task)
        this.proxy.addTask(task)
      })
      selectedTasks.forEach(task => {
        this.proxy.removeTask(task)
        this.proxy.addTask(task, '#EEB516')
      })
    }

  }

  render() {
    return (
      <div id="map"></div>
    )
  }
}

function mapStateToProps(state, ownProps) {

  const { taskLists, unassignedTasks, polylineEnabled } = state

  const tasks = unassignedTasks.slice(0)
  _.forEach(taskLists, taskList => taskList.items.forEach(task => tasks.push(task)))

  let polylines = {}
  _.forEach(taskLists, taskList => {
    polylines[taskList.username] = taskList.polyline
  })

  return {
    tasks,
    polylines,
    polylineEnabled,
    showFinishedTasks: state.taskFinishedFilter,
    selectedTags: state.tagsFilter.selectedTagsList,
    showUntaggedTasks: state.tagsFilter.showUntaggedTasks,
    showCancelledTasks: state.taskCancelledFilter,
    selectedTasks: state.selectedTasks
  }
}

export default connect(mapStateToProps)(LeafletMap)
