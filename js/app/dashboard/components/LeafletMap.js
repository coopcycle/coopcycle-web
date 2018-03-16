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

    let { tasks, showFinishedTasks } = this.props

    if (!showFinishedTasks) {
      tasks = _.filter(tasks, (task) => { return task.status === 'TODO' })
    }

    _.forEach(tasks, task => this.proxy.addTask(task))
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
    const { polylineEnabled, polylines, tasks, selectedTags } = this.props

    _.forEach(polylines, (polyline, username) => this.proxy.setPolyline(username, polyline))
    _.forEach(polylineEnabled, (enabled, username) => {
      if (enabled) {
        this.proxy.showPolyline(username, polylines[username])
      } else {
        this.proxy.hidePolyline(username)
      }
    })

    if (prevProps.showFinishedTasks !== this.props.showFinishedTasks) {
      const finishedTasks = _.filter(tasks, task => task.status !== 'TODO')
      if (this.props.showFinishedTasks) {
        _.forEach(finishedTasks, task => this.proxy.addTask(task))
      } else {
        _.forEach(finishedTasks, task => this.proxy.hideTask(task))
      }
    }

    if ( prevProps.selectedTags !== this.props.selectedTags ) {

      let toAdd = _.map(_.filter(this.props.selectedTags, tag => prevProps.selectedTags.indexOf(tag) < 0), tag => tag.name)

      let toDelete = _.map(_.filter(prevProps.selectedTags, tag => this.props.selectedTags.indexOf(tag) < 0 ), tag => tag.name)

      for (let i=0; i<tasks.length; i++) {

        let taskTagsName = _.map(tasks[i].tags, tag => tag.name)

        if ( taskTagsName.some( tagName => toDelete.indexOf(tagName) > -1)) {
          this.proxy.hideTask(tasks[i])
        } else if (taskTagsName.some( tagName => toAdd.indexOf(tagName) > -1)) {
          this.proxy.addTask(tasks[i])
        }
      }
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
    showFinishedTasks: state.tasksFilters.showFinishedTasks,
    selectedTags: state.tagsFilters.selectedTags,
  }
}

export default connect(mapStateToProps)(LeafletMap)
