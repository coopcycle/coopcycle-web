import React, { Component } from 'react'
import { render } from 'react-dom'
import { connect } from 'react-redux'
import MapHelper from '../../MapHelper'
import MapProxy from './MapProxy'
import _ from 'lodash'
import moment from 'moment'
import classNames from 'classnames'

import { setCurrentTask, assignAfter, selectTask, selectTasksByIds, toggleTask } from '../redux/actions'
import { CourierMapLayer, TaskMapLayer, PolylineMapLayer, ClustersMapToggle } from './MapLayers'
import { selectVisibleTaskIds } from '../redux/selectors'
import { selectAllTasks } from '../../coopcycle-frontend-js/logistics/redux'

const sortByBefore = task => moment(task.before)

const GroupHeading = ({ tasks }) => {
  const task = _.first(tasks)

  if (task.orgName) {
    return (
      <div className="mb-2 px-2">
        <strong className="d-block">{ `${task.orgName} (${tasks.length})` }</strong>
        <small className="text-muted">{ task.address.streetAddress }</small>
      </div>
    )
  }

  return (
    <strong className="d-block mb-2 px-2">{ `${task.address.streetAddress} (${tasks.length})` }</strong>
  )
}

const TASKS_PER_PAGE = 5

const GroupTable = ({ tasks, onEditClick, onMouseEnter, onMouseLeave }) => {

  const [ page, setPage ] = React.useState(1)

  const pages = Math.ceil(tasks.length / TASKS_PER_PAGE)

  // Create an array starting at 1
  const pagesArray =
    Array.from({ length: pages }, (value, index) => index + 1)

  const offset = (page - 1) * TASKS_PER_PAGE
  const tasksForPage = tasks.slice(offset, offset + TASKS_PER_PAGE)

  return (
    <div>
      <table className="table table-hover table-condensed mb-2">
        <tbody>
        { tasksForPage.map(task =>
          <tr key={ task['@id'] } className="py-1"
            onMouseEnter={() => {
              onMouseEnter(task)
            }}
            onMouseLeave={ onMouseLeave }>
            <td>
              <a href="#" onClick={ (e) => {
                e.preventDefault()
                onEditClick(task)
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
      { pages > 1 && (
      <div className="text-right mr-2">
        { pagesArray.map(p =>
          <a key={ `p-${p}` }
            href="#"
            className={ classNames({
              'p-2': true,
              'bg-light': p === page
            }) }
            onClick={ e => {
              e.preventDefault()
              setPage(p)
            }}
          >{ p }</a>
        )}
      </div>
      ) }
    </div>
  )
}

const isDoingOrDone = task => _.includes(['DOING', 'DONE'], task.status)

class GroupPopupContent extends React.Component {

  render() {

    const tasksByAddress = _.mapValues(
      _.groupBy(this.props.clusterTasks, (task) => `${task.address['@id']}|${task.orgName}` ),
      (tasks) => _.sortBy(tasks, [ sortByBefore ])
    )

    return (
      <div className="mt-5 mb-3">
        <div className="leaflet-popup-pickup-group-content">
        { _.map(tasksByAddress, (tasks, key) =>
          <div key={ key } className="mb-3">
            <GroupHeading tasks={ tasks } />
            <GroupTable tasks={ tasks }
              onEditClick={ this.props.onEditClick }
              onMouseEnter={ this.props.onMouseEnter }
              onMouseLeave={ this.props.onMouseLeave } />
          </div>
        )}
        </div>
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
      onLoad: props.onLoad,
      polygonManagement: true,
    })

    const proxy = new MapProxy(LMap, {
      useAvatarColors: props.useAvatarColors,
      onEditClick: props.setCurrentTask,
      toggleTaskOnMarkerClick: (task) => props.toggleTask(task),
      selectTaskOnMarkerClick: (taskId) => props.selectTasksByIds([taskId]),
      onTaskMouseDown: task => {
        if (task.isAssigned) {
          proxy.disableDragging()
          fromTask.current = task
        }
      },
      onTaskMouseOver: task => {
        if (task.isAssigned) {
          proxy.enableConnect(task)
        }
        if (fromTask.current && task !== fromTask.current && !task.isAssigned) {
          toTask.current = task
          proxy.enableConnect(task, true)
        }
      },
      onTaskMouseOut: (task) => {
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
        const taskIds = markers.map(marker => marker.options.task['@id'])
        props.selectTasksByIds(taskIds)
      },
      onPickupClusterClick: (a) => {

        const childMarkers = a.layer.getAllChildMarkers()
        const tasks = childMarkers.map(m => m.options.task)

        const el = document.createElement('div')

        render(<GroupPopupContent
          onEditClick={ proxy.onEditClick }
          clusterTasks={ tasks }
          onMouseEnter={ task => {
            proxy.pointToNext(task, a.latlng)
          }}
          onMouseLeave={ () => {
            proxy.hideNext()
          }}
          />, el)

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
        toggleTask={ this.props.toggleTask }
        useAvatarColors={ this.props.useAvatarColors }
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
    useAvatarColors: state.settings.useAvatarColors,
  }
}

function mapDispatchToProps (dispatch) {

  return {
    setCurrentTask: task => dispatch(setCurrentTask(task)),
    assignAfter: (username, task, after) => dispatch(assignAfter(username, task, after)),
    selectTask: task => dispatch(selectTask(task)),
    selectTasksByIds: taskIds => dispatch(selectTasksByIds(taskIds)),
    toggleTask: task => dispatch(toggleTask(task, true)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(LeafletMap)
