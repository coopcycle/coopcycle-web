import React from 'react'
import { useDispatch, useSelector } from 'react-redux'
import moment from 'moment'
import { Droppable } from "@hello-pangea/dnd"
import { useTranslation } from 'react-i18next'
import _ from 'lodash'
import { Tooltip } from 'antd'
import Popconfirm from 'antd/lib/popconfirm'
import classNames from 'classnames'

import Task from './Task'

import Avatar from '../../components/Avatar'
import { unassignTasks, togglePolyline, optimizeTaskList, onlyFilter, toggleTaskListPanelExpanded } from '../redux/actions'
import { selectExpandedTaskListPanelsIds, selectPolylineEnabledByUsername, selectTrailersLoading, selectVehiclesLoading, selectVisibleTaskIds } from '../redux/selectors'
import Tour from './Tour'
import { getDroppableListStyle } from '../utils'
import ProgressBar from './ProgressBar'
import { selectTaskListByUsername, selectTaskListTasksByUsername, selectTaskListVolumeUnits, selectTaskListWeight, selectVehicleById } from '../../../shared/src/logistics/redux/selectors'
import PolylineIcon from './icons/PolylineIcon'
import {default as VehicleIcon} from './icons/Vehicle'
import {default as TrailerIcon} from './icons/Trailer'
import ExtraInformations from './TaskCollectionDetails'
import Vehicle from './Vehicle'
import Trailer from './Trailer'
import { useContextMenu } from 'react-contexify'
import TrailerSelectMenu from './context-menus/TrailerSelectMenu'

moment.locale($('html').attr('lang'))

const showVehicleMenu = useContextMenu({
  id: 'vehicle-selectmenu'
}).show

const TaskOrTour = ({ item, draggableIndex, unassignTasksFromTaskList }) => {

  if (item.startsWith('/api/tours')) {
    return (<Tour tourId={ item } draggableIndex={ draggableIndex } />)
  } else {
    return (<Task taskId={ item } draggableIndex={ draggableIndex } onRemove={ item => unassignTasksFromTaskList(item) } />)
  }
}

// OPTIMIZATION
// Avoid useless re-rendering when starting to drag
// @see https://egghead.io/lessons/react-optimize-performance-in-@hello-pangea/dnd-with-shouldcomponentupdate-and-purecomponent
class InnerList extends React.Component {

  shouldComponentUpdate(nextProps) {
    if (nextProps.items === this.props.items) {
      return false
    }

    return true
  }

  render() {
    return _.map(this.props.items,
      (item, index) => <TaskOrTour
        key={ item }
        item={ item }
        draggableIndex={ index }
        unassignTasksFromTaskList={ this.props.unassignTasksFromTaskList }
      />)
  }
}

// OPTIMIZATION
// Use React.memo to avoid re-renders when percentage hasn't changed
const ProgressBarMemo = React.memo(({
  completedTasks, inProgressTasks, cancelledTasks,
  failureTasks, tasks, t
}) => {

    const completedPer = completedTasks / tasks * 100
    const inProgressPer = inProgressTasks / tasks * 100
    const failurePer = failureTasks / tasks * 100
    const cancelledPer = cancelledTasks / tasks * 100
    const title = (
      <table style={{ width: '100%' }}>
        <tbody>
          <tr>
            <td style={{ paddingRight: '10px' }}><span style={{ color: '#28a745' }}>●</span> {t('ADMIN_DASHBOARD_TOOLTIP_COMPLETED')}</td>
            <td style={{ textAlign: 'right' }}>{completedTasks}</td>
          </tr>
          <tr>
            <td style={{ paddingRight: '10px' }}><span style={{ color: '#ffc107' }}>●</span> {t('ADMIN_DASHBOARD_TOOLTIP_FAILED')}</td>
            <td style={{ textAlign: 'right' }}>{failureTasks}</td>
          </tr>
          <tr>
            <td style={{ paddingRight: '10px' }}><span style={{ color: '#dc3545' }}>●</span> {t('ADMIN_DASHBOARD_TOOLTIP_CANCELLED')}</td>
            <td style={{ textAlign: 'right' }}>{cancelledTasks}</td>
          </tr>
          <tr>
            <td style={{ paddingRight: '10px' }}><span style={{ color: '#337ab7' }}>●</span> {t('ADMIN_DASHBOARD_TOOLTIP_IN_PROGRESS')}</td>
            <td style={{ textAlign: 'right' }}>{inProgressTasks}</td>
          </tr>
          <tr>
            <td>───</td>
            <td></td>
          </tr>
          <tr>
            <td style={{ paddingRight: '10px' }}>{t('ADMIN_DASHBOARD_TOOLTIP_TOTAL')}</td>
            <td style={{ textAlign: 'right' }}>{tasks}</td>
          </tr>
        </tbody>
      </table>
    )

    return (
        <Tooltip title={title}>
          <div>
            <ProgressBar width="100%" height="8px" backgroundColor="white" segments={[
              {value: `${completedPer}%`, color: '#28a745'},
              {value: `${failurePer}%`, color: '#ffc107'},
              {value: `${cancelledPer}%`, color: '#dc3545'},
              {value: `${inProgressPer}%`, color: '#337ab7'},
            ]} />
          </div>
        </Tooltip>
    )
  })

export const TaskList = ({ uri, username, distance, duration, taskListsLoading }) => {
  const dispatch = useDispatch()
  const unassignTasksFromTaskList = (username => tasks => dispatch(unassignTasks(username, tasks)))(username)

  const taskList = useSelector(state => selectTaskListByUsername(state, {username: username}))
  const items = taskList.items
  const tasks = useSelector(state => selectTaskListTasksByUsername(state, {username: username}))
  const visibleTaskIds = useSelector(selectVisibleTaskIds)

  const vehiclesLoading = useSelector(selectVehiclesLoading)
  const trailersLoading = useSelector(selectTrailersLoading)
  const showTrailerMenu = useContextMenu({
    id: `trailer-selectmenu-${username}`
  }).show

  const visibleTasks = tasks.filter(task => {
    return _.includes(visibleTaskIds, task['@id'])
  })

  const expandedTaskListPanelsIds = useSelector(selectExpandedTaskListPanelsIds)
  const isExpanded = expandedTaskListPanelsIds.includes(taskList['@id'])

  const polylineEnabled = useSelector(selectPolylineEnabledByUsername(username))

  const { t } = useTranslation()

  const uncompletedTasks = _.filter(visibleTasks, t => t.status === 'TODO')
  const completedTasks = _.filter(visibleTasks, t => t.status === 'DONE')
  const inProgressTasks = _.filter(visibleTasks, t => t.status === 'DOING')
  const failureTasks = _.filter(visibleTasks, t => t.status === 'FAILED')
  const cancelledTasks = _.filter(visibleTasks, t => t.status === 'CANCELLED')
  const incidentReported = _.filter(visibleTasks, t => t.hasIncidents)

  const vehicle = useSelector(state => selectVehicleById(state, taskList.vehicle))
  const weight = useSelector(state => selectTaskListWeight(state, {username: username}))
  const volumeUnits = useSelector(state => selectTaskListVolumeUnits(state, {username: username}))

  return (
    <div>
      <div className="pl-2 task-list__header" onClick={() => dispatch(toggleTaskListPanelExpanded(taskList['@id']))}>
          <div className="mb-1 d-flex align-items-center task-list__badges">
            <Avatar username={ username } size="24" className="ml-2" />
            <strong className="mr-2">{ username }</strong>
            <span className="badge">{ tasks.length }</span>
            <Vehicle vehicleId={taskList.vehicle} />
            <Trailer trailerId={taskList.trailer} />
          </div>
          <div className="mb-1" >
            {visibleTasks.length > 0 && (
              <div style={{ width: '80%' }}>
                <ProgressBarMemo
                    completedTasks={ completedTasks.length }
                    tasks={ visibleTasks.length }
                    inProgressTasks={ inProgressTasks.length }
                    incidentReported={ incidentReported.length }
                    failureTasks={ failureTasks.length }
                    cancelledTasks={ cancelledTasks.length }
                    t={t.bind(this)}
                  />
              </div>
              ) }
              {incidentReported.length > 0 && <div onClick={(e) => {
                dispatch(onlyFilter('showIncidentReportedTasks'))
                e.stopPropagation()
              }}>
              <Tooltip title="Incident(s)">
                <span className='fa fa-warning text-warning' /> <span className="text-secondary">({incidentReported.length})</span>
              </Tooltip>
            </div>}
          </div>
          <ExtraInformations
            duration={duration}
            distance={distance}
            weight={weight}
            volumeUnits={volumeUnits}
            vehicleMaxWeight={vehicle?.maxWeight}
            vehicleMaxVolumeUnits={vehicle?.volumeUnits}
          />
      </div>
      <div className={classNames("panel-collapse collapse",{"in": isExpanded})}>
        <div className="d-flex align-items-center mt-2 mb-2">
          <a
            className='tasklist__actions--icon ml-3'
            onClick={ (e) => showVehicleMenu({event: e, props: {username: username}}) }
          >
            <VehicleIcon />
          </a>
          { taskList.vehicle ?
            <a
              className='tasklist__actions--icon ml-3'
              onClick={ (e) => showTrailerMenu({event: e, props: {username: username}}) }
            >
              <TrailerIcon />
            </a> :
            null
          }
          <a
            className='tasklist__actions--icon ml-3'
            onClick={ () => dispatch(togglePolyline(username)) }
          >
            <PolylineIcon fillColor={polylineEnabled ? '#EEB516' : null} />
          </a>
          { tasks.length > 0 ?
            <>
              <a
                className="ml-3 tasklist__actions--icon d-flex align-items-center justify-content-center"
                title="Optimize"
                style={{
                  visibility: tasks.length > 1 ? 'visible' : 'hidden'
                }}
                onClick={ e => {
                  e.preventDefault()
                  dispatch(optimizeTaskList({'@id': uri, username: username}))
                }}
              >
                <i className="fa fa-2x fa-bolt"></i>
              </a>
              <Popconfirm
                placement="left"
                title={ t('ADMIN_DASHBOARD_UNASSIGN_ALL_TASKS') }
                onConfirm={ () => dispatch(unassignTasks(username, uncompletedTasks)) }
                okText={ t('CROPPIE_CONFIRM') }
                cancelText={ t('ADMIN_DASHBOARD_CANCEL') }>
                <a href="#"
                  className="ml-3 tasklist__actions--icon d-flex align-items-center justify-content-center"
                  onClick={ e => e.preventDefault() }>
                  <i className="fa fa-2x fa-times"></i>
                </a>
              </Popconfirm>
            </> : null
            }
        </div>
        <Droppable
          droppableId={ `assigned:${username}` }
          key={tasks.length} // assign a mutable key to trigger a re-render when inserting a nested droppable (for example : a tour)
          isDropDisabled={ taskListsLoading }
        >
          {(provided, snapshot) => (
            <div ref={ provided.innerRef }
              className='taskList__tasks list-group m-0'
              { ...provided.droppableProps }
              style={getDroppableListStyle(snapshot.isDraggingOver)}
            >
              <InnerList
                items={ items }
                unassignTasksFromTaskList={ unassignTasksFromTaskList }
                username={ username } />
              { provided.placeholder }
            </div>
          )}
        </Droppable>
      </div>
      { !trailersLoading && !vehiclesLoading && taskList.vehicle ?
        <TrailerSelectMenu username={username} vehicleId={taskList.vehicle} /> :
        null
      }
    </div>
  )
}





export default TaskList
