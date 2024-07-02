import React from 'react'
import { connect } from 'react-redux'
import _ from 'lodash'

import { selectVisibleTaskIds, selectHiddenTaskIds, selectPolylines, selectAsTheCrowFlies,
  selectPositions, selectSelectedTasks, selectRestaurantAddressIds, selectTourIdToColorMap, selectSettings } from '../redux/selectors'
import { selectAllTasks } from '../../coopcycle-frontend-js/logistics/redux'

import { useMap } from './LeafletMap'
import { selectTaskIdToTourIdMap, selectTourPolylines } from '../../../shared/src/logistics/redux/selectors'
import { mapColorHash } from '../../MapHelper'

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

const TaskLayer = ({ tasks, visibleTaskIds, hiddenTaskIds, selectedTasks, /*pickupGroups,*/ restaurantAddressIds, useAvatarColors, polylineEnabled, tourPolylinesEnabled, taskIdToTourIdMap, tourIdToColorMap }) => {

  const map = useMap()

  React.useEffect(() => {

    const visibleTasks = _.intersectionWith(tasks, visibleTaskIds, (task, id) => task['@id'] === id)
    const hiddenTasks  = _.intersectionWith(tasks, hiddenTaskIds,  (task, id) => task['@id'] === id)

    visibleTasks.forEach(task => {
      const selected = -1 !== selectedTasks.indexOf(task)
      const isRestaurantAddress = -1 !== restaurantAddressIds.indexOf(task.address['@id'])
      map.addTask(task, useAvatarColors, selected, isRestaurantAddress, polylineEnabled, tourPolylinesEnabled, taskIdToTourIdMap, tourIdToColorMap)
    })

    hiddenTasks.forEach(task => map.hideTask(task))

  }, [ useAvatarColors, tasks, visibleTaskIds, hiddenTaskIds, selectedTasks, polylineEnabled, tourPolylinesEnabled, taskIdToTourIdMap ])

  return null
}

const PolylineLayer = ({ polylines, asTheCrowFlies, polylineEnabled, tourPolylines, tourPolylinesEnabled, polylineStyle, tourIdToColorMap }) => {

  const map = useMap()

  React.useEffect(() => {

    _.forEach(polylines, (polyline, username) => map.setPolyline(username, polyline, mapColorHash.hex(username)))

    _.forEach(tourPolylines, (polyline, tourId) => map.setPolyline(tourId, polyline, tourIdToColorMap.get(tourId)))

    _.forEach(asTheCrowFlies, (polyline, key) => {
      let color
      if (key.startsWith('/api/tours')) {
        color = tourIdToColorMap.get(key) // for tours the ID is the key
      } else {
        color = mapColorHash.hex(key) // for tasklist the username is the key
      }
      map.setPolylineAsTheCrowFlies(key, polyline, color)
    })

    _.forEach(polylineEnabled, (enabled, username) => {
      if (enabled) {
        map.showPolyline(username, polylineStyle)
      } else {
        map.hidePolyline(username)
      }
    })

    _.forEach(tourPolylinesEnabled, (enabled, tourId) => {
      if (enabled) {
        map.showPolyline(tourId, polylineStyle)
      } else {
        map.hidePolyline(tourId)
      }
    })

  }, [ polylines, tourPolylines, tourPolylinesEnabled, asTheCrowFlies, polylineEnabled, polylineStyle ])

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

  const { useAvatarColors } = selectSettings(state)

  return {
    tasks: selectAllTasks(state),
    visibleTaskIds: selectVisibleTaskIds(state),
    hiddenTaskIds: selectHiddenTaskIds(state),
    selectedTasks: selectSelectedTasks(state),
    restaurantAddressIds: selectRestaurantAddressIds(state),
    polylineEnabled: state.polylineEnabled,
    tourPolylinesEnabled: state.tourPolylinesEnabled,
    useAvatarColors: useAvatarColors,
    taskIdToTourIdMap: selectTaskIdToTourIdMap(state),
    tourIdToColorMap: selectTourIdToColorMap(state)
  }
}

function mapStateToPropsPolyline(state) {

  return {
    polylines: selectPolylines(state),
    polylineEnabled: state.polylineEnabled,
    tourPolylines: selectTourPolylines(state),
    tourPolylinesEnabled: state.tourPolylinesEnabled,
    polylineStyle: state.settings.polylineStyle,
    asTheCrowFlies: selectAsTheCrowFlies(state),
    tourIdToColorMap: selectTourIdToColorMap(state)
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
