import React, { Component } from 'react'
import { render } from 'react-dom'
import { connect } from 'react-redux'
import MapHelper from '../../MapHelper'
import MapProxy from './MapProxy'
import _ from 'lodash'
import moment from 'moment'
import classNames from 'classnames'

import { setCurrentTask, assignAfter, selectTask, selectTasksByIds } from '../redux/actions'
import { CourierMapLayer, TaskMapLayer, PolylineMapLayer, ClustersMapToggle } from './MapLayers'
import { selectVisibleTaskIds } from '../redux/selectors'
import { selectAllTasks } from '../../coopcycle-frontend-js/logistics/redux'

const sortByBefore = task => moment(task.before)

const GroupHeading = ({ tasks }) => {
  const task = _.first(tasks)

  if (task.orgName) {
    return (
      <div className="mb-2">
        <strong className="d-block">{ task.orgName }</strong>
        <small className="text-muted">{ task.address.streetAddress }</small>
      </div>
    )
  }

  return (
    <strong className="d-block mb-2">{ task.address.streetAddress }</strong>
  )
}

const isDoingOrDone = task => _.includes(['DOING', 'DONE'], task.status)

class GroupPopupContent extends React.Component {

  render() {

    const {
      clusterTaskIds,
      visibleTaskIds,
    } = this.props

    const visibleTasks = _.intersectionWith(this.props.tasks, visibleTaskIds, (task, id) => task['@id'] === id)
    const clusterTasks = _.intersectionWith(visibleTasks, clusterTaskIds, (task, id) => task['@id'] === id)

    const tasksByAddress = _.mapValues(
      _.groupBy(clusterTasks, (task) => `${task.address['@id']}|${task.orgName}` ),
      (tasks) => _.sortBy(tasks, [ sortByBefore ])
    )

    return (
      <div>
        { _.map(tasksByAddress, (tasks, key) =>
          <div key={ key }>
            <GroupHeading tasks={ tasks } />
            <table className="table table-hover table-condensed">
              <tbody>
              { tasks.map(task =>
                <tr key={ task['@id'] } className="py-1">
                  <td>
                    <a href="#" onClick={ (e) => {
                      e.preventDefault()
                      this.props.onEditClick(task)
                    }}
                    >
                      <strong className="mr-2">{ `#${task.id}` }</strong>
                      <span className="text-muted">
                        { `${moment(task.after).format('LT')} — ${moment(task.before).format('LT')}` }
                      </span>
                    </a>
                  </td>
                  <td className="text-right">
                    <i className={ classNames({
                      'fa': true,
                      'fa-check': task.status === 'DONE',
                      'fa-play':  task.status === 'DOING',
                      'd-none': !isDoingOrDone(task) }) }
                    ></i>
                  </td>
                </tr>
              )}
              </tbody>
            </table>
          </div>
        )}
      </div>
    )
  }
}

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
      },
      onPickupClusterClick: (a) => {

        const childMarkers = a.layer.getAllChildMarkers()
        const taskIds = childMarkers.map(m => m.options.task)

        const el = document.createElement('div')

        render(<GroupPopupContent
          clusterTaskIds={ taskIds }
          tasks={ props.tasks }
          visibleTaskIds={ props.visibleTaskIds }
          onEditClick={ proxy.onEditClick } />, el)

        return el
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
        tasks={ this.props.tasks }
        visibleTaskIds={ this.props.visibleTaskIds }
        onLoad={ this.props.onLoad }
        setCurrentTask={ this.props.setCurrentTask }
        assignAfter={ this.props.assignAfter }
        selectTasksByIds={ this.props.selectTasksByIds }
      >
        <CourierMapLayer />
        <TaskMapLayer />
        <PolylineMapLayer />
        <ClustersMapToggle />
      </MapProvider>
    )
  }
}

function mapStateToProps(state) {

  return {
    tasks: selectAllTasks(state),
    visibleTaskIds: selectVisibleTaskIds(state),
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

export default connect(mapStateToProps, mapDispatchToProps)(LeafletMap)
