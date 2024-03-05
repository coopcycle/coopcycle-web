import React from 'react'
import { connect } from 'react-redux'
import moment from 'moment'
import { Draggable, Droppable } from "@hello-pangea/dnd"
import { withTranslation } from 'react-i18next'
import _ from 'lodash'
import { Tooltip } from 'antd'
import Popconfirm from 'antd/lib/popconfirm'
import {
  AccordionItem,
  AccordionItemHeading,
  AccordionItemButton,
  AccordionItemPanel,
} from 'react-accessible-accordion'
import classNames from 'classnames'

import Task from './Task'

import Avatar from '../../components/Avatar'
import { unassignTasks, togglePolyline, optimizeTaskList, onlyFilter } from '../redux/actions'
import { selectFiltersSetting, selectVisibleTaskIds } from '../redux/selectors'
import { makeSelectTaskListItemsByUsername } from '../../coopcycle-frontend-js/logistics/redux'
import Tour from './Tour'
import { getDroppableListStyle } from '../utils'
import ProgressBar from './ProgressBar'

moment.locale($('html').attr('lang'))

const TaskOrTour = ({ item, onRemove }) => {

  if (item['@type'] === 'Tour') {

    return (
      <Tour tour={ item } />
    )
  }

  return (
    <Task
      task={ item }
      onRemove={ item => onRemove(item) } />
  )
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
    return _.map(this.props.items, (item, index) => {
      return (
        <Draggable
          key={ item['@id'] }
          draggableId={ item['@type'] === 'Tour' ? `tour:${item['@id']}`: item['@id']  }
          index={ index }
          >
          {(provided) => (
            <div
              ref={ provided.innerRef }
              { ...provided.draggableProps }
              { ...provided.dragHandleProps }
            >
              <TaskOrTour
                item={ item }
                onRemove={ item => this.props.onRemove(item) }
              />
            </div>
          )}
        </Draggable>
      )
    })
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
            <ProgressBar width="100%" height="8" backgroundColor="white" segments={[
              {value: `${completedPer}%`, color: '#28a745'},
              {value: `${failurePer}%`, color: '#ffc107'},
              {value: `${cancelledPer}%`, color: '#dc3545'},
              {value: `${inProgressPer}%`, color: '#337ab7'},
            ]} />
          </div>
        </Tooltip>
    )
  })

class TaskList extends React.Component {

  remove(task) {
    this.props.unassignTasks(this.props.username, task)
  }

  render() {
    const {
      duration,
      distance,
      username,
      polylineEnabled,
      isEmpty,
    } = this.props

    const { tasks, items } = this.props

    const uncompletedTasks = _.filter(tasks, t => t.status === 'TODO')
    const completedTasks = _.filter(tasks, t => t.status === 'DONE')
    const inProgressTasks = _.filter(tasks, t => t.status === 'DOING')
    const failureTasks = _.filter(tasks, t => t.status === 'FAILED')
    const cancelledTasks = _.filter(tasks, t => t.status === 'CANCELLED')
    const incidentReported = _.filter(tasks, t => t.hasIncidents)

    const durationFormatted = moment.utc()
      .startOf('day')
      .add(duration, 'seconds')
      .format('HH:mm')

    const distanceFormatted = (distance / 1000).toFixed(2) + ' Km'

    return (
      <AccordionItem>
        <AccordionItemHeading>
          <AccordionItemButton>
            <span>
              <Avatar username={ username } size="24" />
              <small className="text-monospace ml-2">
                <strong className="mr-2">{ username }</strong>
                <span className="text-muted">{ `(${tasks.length})` }</span>
              </small>
            </span>
            { tasks.length > 0 && (
            <div style={{ width: '33.3333%' }}>
              <ProgressBarMemo
                  completedTasks={ completedTasks.length }
                  tasks={ tasks.length }
                  inProgressTasks={ inProgressTasks.length }
                  incidentReported={ incidentReported.length }
                  failureTasks={ failureTasks.length }
                  cancelledTasks={ cancelledTasks.length }
                  t={this.props.t.bind(this)}
                />
            </div>
            ) }
            {incidentReported.length > 0 && <div onClick={(e) => {
              this.props.onlyFilter('showIncidentReportedTasks')
              e.stopPropagation()
            }}>
             <Tooltip title="Incident(s)">
                <span className='fa fa-warning text-warning' /> <span className="text-secondary">({incidentReported.length})</span>
              </Tooltip>
            </div>}
            <Popconfirm
              placement="left"
              title={ this.props.t('ADMIN_DASHBOARD_UNASSIGN_ALL_TASKS') }
              onConfirm={ () => this.props.unassignTasks(this.props.username, uncompletedTasks) }
              okText={ this.props.t('CROPPIE_CONFIRM') }
              cancelText={ this.props.t('ADMIN_DASHBOARD_CANCEL') }>
              <a href="#"
                className="text-reset mr-2"
                style={{ visibility: uncompletedTasks.length > 0 ? 'visible' : 'hidden' }}
                onClick={ e => e.preventDefault() }>
                <i className="fa fa-lg fa-times"></i>
              </a>
            </Popconfirm>
          </AccordionItemButton>
        </AccordionItemHeading>
        <AccordionItemPanel>
          { tasks.length > 0 && (
            <div className="d-flex justify-content-between align-items-center p-4">
              <div>
                <strong className="mr-2">{ this.props.t('ADMIN_DASHBOARD_DURATION') }</strong>
                <span>{ durationFormatted }</span>
                <span className="mx-2">—</span>
                <strong className="mr-2">{ this.props.t('ADMIN_DASHBOARD_DISTANCE') }</strong>
                <span>{ distanceFormatted }</span>
              </div>
              <div>
                <a href="#"
                  title="Optimize"
                  style={{
                    color: '#f1c40f',
                    visibility: tasks.length > 1 ? 'visible' : 'hidden'
                  }}
                  onClick={ e => {
                    e.preventDefault()
                    this.props.optimizeTaskList({
                      '@id': this.props.uri,
                      username: this.props.username,
                    })
                  }}
                >
                  <i className="fa fa-2x fa-bolt"></i>
                </a>
                <a role="button"
                  className={ classNames({
                    'ml-3': true,
                    'invisible': tasks.length < 1,
                    'text-muted': !polylineEnabled
                  }) }
                  onClick={ () => this.props.togglePolyline(username) }
                >
                  <i className="fa fa-map fa-2x"></i>
                </a>
              </div>
            </div>
          )}
          <Droppable
            droppableId={ `assigned:${username}` }
            key={tasks.length} // assign a mutable key to trigger a re-render when inserting a nested droppable (for example : a tour)
          >
            {(provided, snapshot) => (
              <div ref={ provided.innerRef }
                className={ classNames({
                  'taskList__tasks': true,
                  'list-group': true,
                  'm-0': true,
                  'taskList__tasks--empty': isEmpty
                }) }
                { ...provided.droppableProps }
                style={getDroppableListStyle(snapshot.isDraggingOver)}
              >
                <InnerList
                  items={ items }
                  onRemove={ task => this.remove(task) }
                  username={ username }
                />
                { provided.placeholder }
              </div>
            )}
          </Droppable>
        </AccordionItemPanel>
      </AccordionItem>
    )
  }
}

const makeMapStateToProps = () => {

  const selectTaskListItemsByUsername = makeSelectTaskListItemsByUsername()

  const mapStateToProps = (state, ownProps) => {

    // items is a prop of mixed task and tours
    const items = selectTaskListItemsByUsername(state, ownProps)

    // we also need a flatten list of tasks
    const tasks = items.reduce((acc, item) => {
      if (item['@type'] === 'Tour') {
        acc.push(...item.items)
      } else {
        acc.push(item)
      }
      return acc
    }, [])

    const visibleTaskIds = _.intersectionWith(
      selectVisibleTaskIds(state),
      tasks.map(task => task['@id'])
    )

    return {
      polylineEnabled: state.polylineEnabled[ownProps.username],
      tasks,
      items,
      isEmpty: items.length === 0 || visibleTaskIds.length === 0,
      distance: ownProps.distance,
      duration: ownProps.duration,
      filters: selectFiltersSetting(state),
    }
  }

  return mapStateToProps
}

function mapDispatchToProps(dispatch) {
  return {
    unassignTasks: (username, tasks) => dispatch(unassignTasks(username, tasks)),
    togglePolyline: (username) => dispatch(togglePolyline(username)),
    optimizeTaskList: (taskList) => dispatch(optimizeTaskList(taskList)),
    onlyFilter: filter => dispatch(onlyFilter(filter))
  }
}

export default connect(makeMapStateToProps, mapDispatchToProps)(withTranslation()(TaskList))
