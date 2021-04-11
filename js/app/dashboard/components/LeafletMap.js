import React, { Component } from 'react'
import { connect } from 'react-redux'
import MapHelper from '../../MapHelper'
import MapProxy from './MapProxy'

import { setCurrentTask, assignAfter, selectTask, selectTasksByIds } from '../redux/actions'
import { CourierMapLayer, TaskMapLayer, PolylineMapLayer, ClustersMapToggle } from './MapLayers'

const MapContext = React.createContext([ null, () => {} ])

const MapProvider = (props) => {

  const [ map, setMap ] = React.useState(null);

  const fromTask = React.useRef(null);
  const toTask   = React.useRef(null);

  React.useEffect(() => {

    const LMap = MapHelper.init('map', {
      onLoad: props.onLoad
    })

    const proxy = new MapProxy(LMap, {
      onEditClick: props.setCurrentTask,
      onTaskMouseDown: task => {
        if (task.isAssigned) {
          proxy.disableDragging()
          fromTask.current = task
        }
      },
      onTaskMouseOver: task => {
        if (task.isAssigned) {
          proxy.enableConnect(task)
          proxy.showPolyline(task.assignedTo, 'as_the_crow_flies')
        }
        if (fromTask.current && task !== fromTask.current && !task.isAssigned) {
          toTask.current = task
          proxy.enableConnect(task, true)
        }
      },
      onTaskMouseOut: (task) => {
        if (task.isAssigned) {
          proxy.hidePolyline(task.assignedTo)
        }
        toTask.current = null
        proxy.disableConnect(task)
      },
      onMouseMove: (e) => {
        if (fromTask.current) {
          const targetLatLng = !!toTask.current ? proxy.toLatLng(toTask.current) : e.latlng
          proxy.setDrawPolyline(proxy.toLatLng(fromTask.current), targetLatLng, !!toTask.current)
          proxy.enableConnect(fromTask.current, !!toTask.current)
        }
      },
      onMouseUp: () => {

        if (!!fromTask.current && !!toTask.current) {
          props.assignAfter(fromTask.current.assignedTo, toTask.current, fromTask.current)
        }

        if (!!fromTask.current) {
          proxy.disableConnect(fromTask.current)
        }
        if (!!toTask.current) {
          proxy.disableConnect(toTask.current)
        }

        fromTask.current = null
        toTask.current = null

        proxy.clearDrawPolyline()
        proxy.enableDragging()
      },
      onMarkersSelected: markers => {
        const taskIds = markers.map(marker => marker.options.task)
        props.selectTasksByIds(taskIds)
      }
    })

    setMap(proxy)

  }, [])

  return (
    <MapContext.Provider value={ map }>
      <div id="map"></div>
      { map && props.children }
    </MapContext.Provider>
  )
}

export const useMap = () => React.useContext(MapContext)

class LeafletMap extends Component {

  render() {

    return (
      <MapProvider
        onLoad={ this.props.onLoad }
        setCurrentTask={ this.props.setCurrentTask }
        assignAfter={ this.props.assignAfter }
        selectTasksByIds={ this.props.selectTasksByIds }>
        <CourierMapLayer />
        <TaskMapLayer />
        <PolylineMapLayer />
        <ClustersMapToggle />
      </MapProvider>
    )
  }
}

function mapDispatchToProps (dispatch) {
  return {
    setCurrentTask: task => dispatch(setCurrentTask(task)),
    assignAfter: (username, task, after) => dispatch(assignAfter(username, task, after)),
    selectTask: task => dispatch(selectTask(task)),
    selectTasksByIds: taskIds => dispatch(selectTasksByIds(taskIds)),
  }
}

export default connect(() => ({}), mapDispatchToProps)(LeafletMap)
