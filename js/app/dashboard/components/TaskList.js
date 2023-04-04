import React from 'react'
import { connect } from 'react-redux'
import moment from 'moment'
import { Draggable, Droppable } from "react-beautiful-dnd"
import { withTranslation } from 'react-i18next'
import _ from 'lodash'
import { Progress, Tooltip } from 'antd'
import Popconfirm from 'antd/lib/popconfirm'
import {
  AccordionItem,
  AccordionItemHeading,
  AccordionItemButton,
  AccordionItemPanel,
} from 'react-accessible-accordion'
import classNames from 'classnames'

import Task from './Task'
import Tour from './Tour'

import Avatar from '../../components/Avatar'
import { unassignTasks, togglePolyline, optimizeTaskList } from '../redux/actions'
import { selectVisibleTaskIds } from '../redux/selectors'
import { makeSelectTaskListItemsByUsername } from '../../coopcycle-frontend-js/logistics/redux'

moment.locale($('html').attr('lang'))

const TaskOrTour = ({ item, onRemove, unassignTasks, username }) => {

  if (item['@type'] === 'Tour') {

    return (
      <Tour
        tour={ item }
        tasks={ item.items }
        username={ username }
        unassignTasks={ unassignTasks } />
    )
  }

  return (
    <Task
      task={ item }
      assigned={ true }
      onRemove={ item => onRemove(item) } />
  )
}

// OPTIMIZATION
// Avoid useless re-rendering when starting to drag
// @see https://egghead.io/lessons/react-optimize-performance-in-react-beautiful-dnd-with-shouldcomponentupdate-and-purecomponent
class InnerList extends React.Component {

  shouldComponentUpdate(nextProps) {
    if (nextProps.tasks === this.props.tasks) {
      return false
    }

    return true
  }

  render() {
    return _.map(this.props.tasks, (task, index) => {
      return (
        <Draggable key={ task['@id'] } draggableId={ task['@id'] } index={ index }>
          {(provided) => (
            <div
              ref={ provided.innerRef }
              { ...provided.draggableProps }
              { ...provided.dragHandleProps }
            >
              <TaskOrTour
                item={ task }
                assigned={ true }
                onRemove={ task => this.props.onRemove(task) }
                username={ this.props.username }
                unassignTasks={ this.props.unassignTasks } />
            </div>
          )}
        </Draggable>
      )
    })
  }
}

// OPTIMIZATION
// Use React.memo to avoid re-renders when percentage hasn't changed
const ProgressBar = React.memo(({ completedTasks, tasks }) => {

  return (
    <Tooltip title={ `${completedTasks} / ${tasks}` }>
      <Progress percent={ Math.round((completedTasks * 100) / tasks) } size="small" />
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

    const { tasks } = this.props

    const uncompletedTasks = _.filter(tasks, t => t.status === 'TODO')
    const completedTasks = _.filter(tasks, t => t.status === 'DONE')

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
              <ProgressBar completedTasks={ completedTasks.length } tasks={ tasks.length } />
            </div>
            ) }
            <Popconfirm
              placement="left"
              title={ this.props.t('ADMIN_DASHBOARD_UNASSIGN_ALL_TASKS') }
              onConfirm={ () => this.props.unassignTasks(this.props.username, uncompletedTasks) }
              okText={ this.props.t('CROPPIE_CONFIRM') }
              cancelText={ this.props.t('ADMIN_DASHBOARD_CANCEL') }>
              <a href="#"
                className="p-2"
                style={{ visibility: uncompletedTasks.length > 0 ? 'visible' : 'hidden' }}
                onClick={ e => e.preventDefault() }>
                <i className="fa fa-lg fa-close"></i>
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
          <Droppable droppableId={ `assigned:${username}` }>
            {(provided) => (
              <div ref={ provided.innerRef }
                className={ classNames({
                  'taskList__tasks': true,
                  'list-group': true,
                  'm-0': true,
                  'taskList__tasks--empty': isEmpty
                }) }
                { ...provided.droppableProps }
              >
                <InnerList
                  tasks={ tasks }
                  onRemove={ task => this.remove(task) }
                  unassignTasks={ this.props.unassignTasks }
                  username={ username } />
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

    const items = selectTaskListItemsByUsername(state, ownProps)

    const visibleTaskIds = _.intersectionWith(
      selectVisibleTaskIds(state),
      items.map(task => task['@id'])
    )

    return {
      polylineEnabled: state.polylineEnabled[ownProps.username],
      tasks: items,
      isEmpty: items.length === 0 || visibleTaskIds.length === 0,
      distance: ownProps.distance,
      duration: ownProps.duration,
      filters: state.settings.filters,
    }
  }

  return mapStateToProps
}

function mapDispatchToProps(dispatch) {
  return {
    unassignTasks: (username, tasks) => dispatch(unassignTasks(username, tasks)),
    togglePolyline: (username) => dispatch(togglePolyline(username)),
    optimizeTaskList: (taskList) => dispatch(optimizeTaskList(taskList)),
  }
}

export default connect(makeMapStateToProps, mapDispatchToProps)(withTranslation()(TaskList))
