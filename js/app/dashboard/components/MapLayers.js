import React from 'react'
import { connect } from 'react-redux'
import _ from 'lodash'

import { selectVisibleTaskIds, selectHiddenTaskIds, selectPolylines, selectAsTheCrowFlies, selectPositions, selectSelectedTasks } from '../redux/selectors'
import { selectAllTasks } from '../../coopcycle-frontend-js/logistics/redux'

import { useMap } from './LeafletMap'

const CourierLayer = ({ positions }) => {

  const map = useMap()

  React.useEffect(() => {

    positions.forEach(position => {
      const { username, coords, lastSeen, offline } = position
      map.setGeolocation(username, coords, lastSeen, offline)
    })

  }, [ positions ])

  return null
}

const TaskLayer = ({ tasks, visibleTaskIds, hiddenTaskIds, selectedTasks }) => {

  const map = useMap()

  React.useEffect(() => {

    const visibleTasks = _.intersectionWith(tasks, visibleTaskIds, (task, id) => task['@id'] === id)
    const hiddenTasks  = _.intersectionWith(tasks, hiddenTaskIds,  (task, id) => task['@id'] === id)

    visibleTasks.forEach(task => map.addTask(task))
    hiddenTasks.forEach(task => map.hideTask(task))

    selectedTasks.forEach(task => map.addTask(task, '#EEB516'))

  }, [ tasks, visibleTaskIds, hiddenTaskIds, selectedTasks ])

  return null
}

const PolylineLayer = ({ polylines, asTheCrowFlies, polylineEnabled, polylineStyle }) => {

  const map = useMap()

  React.useEffect(() => {

    _.forEach(polylines,      (polyline, username) => map.setPolyline(username, polyline))
    _.forEach(asTheCrowFlies, (polyline, username) => map.setPolylineAsTheCrowFlies(username, polyline))

    _.forEach(polylineEnabled, (enabled, username) => {
      if (enabled) {
        map.showPolyline(username, polylineStyle)
      } else {
        map.hidePolyline(username)
      }
    })

  }, [ polylines, asTheCrowFlies, polylineEnabled, polylineStyle ])

  return null
}

const ClustersToggle = ({ clustersEnabled }) => {

  const map = useMap()

  React.useEffect(() => {

    if (clustersEnabled) {
      map.showClusters()
    } else {
      map.hideClusters()
    }

  }, [ clustersEnabled ])

  return null
}

function mapStateToPropsTask(state) {

  return {
    tasks: selectAllTasks(state),
    visibleTaskIds: selectVisibleTaskIds(state),
    hiddenTaskIds: selectHiddenTaskIds(state),
    selectedTasks: selectSelectedTasks(state),
  }
}

function mapStateToPropsPolyline(state) {

  return {
    polylines: selectPolylines(state),
    polylineEnabled: state.polylineEnabled,
    polylineStyle: state.settings.polylineStyle,
    asTheCrowFlies: selectAsTheCrowFlies(state),
  }
}

function mapStateToPropsClusters(state) {

  return {
    clustersEnabled: state.settings.clustersEnabled,
  }
}

export const CourierMapLayer = connect(state => ({ positions: selectPositions(state) }))(CourierLayer)
export const PolylineMapLayer = connect(mapStateToPropsPolyline)(PolylineLayer)
export const TaskMapLayer = connect(mapStateToPropsTask)(TaskLayer)
export const ClustersMapToggle = connect(mapStateToPropsClusters)(ClustersToggle)
