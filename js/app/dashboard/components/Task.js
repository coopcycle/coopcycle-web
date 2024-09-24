import React from 'react'
import { connect } from 'react-redux'
import { withTranslation, useTranslation } from 'react-i18next'
import moment from 'moment'
import { useContextMenu } from 'react-contexify'
import _ from 'lodash'
import { Draggable } from "@hello-pangea/dnd"


import { setCurrentTask, toggleTask, selectTask } from '../redux/actions'
import { selectSettings, selectVisibleTaskIds } from '../redux/selectors'
import { selectSelectedDate, selectTasksWithColor } from '../../coopcycle-frontend-js/logistics/redux'

import { addressAsText } from '../utils'
import TaskEta from './TaskEta'
import { getTaskPackages, getTaskVolumeUnits, selectTaskById } from '../../../shared/src/logistics/redux/selectors'
import { formatVolumeUnits, formatWeight } from '../redux/utils'

moment.locale($('html').attr('lang'))

const TaskComments = ({ task }) => {
  switch(task.type) {
    case 'PICKUP':
      if (task.metadata?.order_notes && task.metadata.order_notes.length){
        return <i className="fa fa-comments ml-2"></i>;
      }
      return null;
    case 'DROPOFF':
      if (task.address.description && task.address.description.length) {
        return <i className="fa fa-comments ml-2"></i>;
      }
      return null;
    default:
      return null;
  }
}

const TaskCaption = ({ task }) => {

  const { t } = useTranslation()

  return (
    <span>
      <span className="mr-1">
        <span className="text-monospace font-weight-bold">
          { task.metadata?.order_number ?
            <>
              {
                task.metadata?.delivery_position ?
                <>{task.metadata.order_number}-{task.metadata.delivery_position}</>
                : task.metadata.order_number
              }
            </>
            : `#${ task.id }`
          }
        </span>
        {/* keep the task ID displayed for the web dispatcher while migrating the client code as the rider sees the task ID in the app */}
        <span className='text-muted ml-1'>
          {`#${ task.id }`}
        </span>
      </span>
      { (task.orgName && !_.isEmpty(task.orgName)) && (
        <span>
          <span>{ task.orgName }</span>
          <span className="mx-1">›</span>
        </span>
      ) }
      <span>{ addressAsText(task.address) }</span>
      <span className="mx-1">·</span>
      <span>
      { t('ADMIN_DASHBOARD_TASK_TIME_RANGE', {
        after: moment(task.after).format('LT'),
        before: moment(task.before).format('LT'),
      }) }
      </span>
    </span>
  )
}

const TaskAttrs = ({ task }) => {
  if (task.images && task.images.length > 0) {

    return (
      <span className="task__attrs">
        <i className="fa fa-camera"></i>
      </span>
    )
  }

  return null
}

const TaskTags = ({ task }) => {
  if (task.tags.length > 0) {

    return (
      <span className="task__tags">
        { task.tags.map(tag => (
          <i key={ tag.slug } className="fa fa-circle" style={{ color: tag.color }}></i>
        )) }
      </span>
    )
  }

  return null
}

const TaskIconRight = ({ task, onRemove }) => {

  const { t } = useTranslation()
  if (task.isAssigned) {
    switch (task.status) {
    case 'TODO':
      return (
        <a
          href="#"
          className="task__icon task__icon--right"
          onClick={(e) => {
            e.preventDefault()
            e.stopPropagation()
            onRemove(task)
          }}
          title={ t('ADMIN_DASHBOARD_UNASSIGN_TASK', { id: task.id }) }
        ><i className="fa fa-times"></i></a>
      )

    case 'DOING':
      return (
        <span className="task__icon task__icon--right">
          <i className="fa fa-play"></i>
        </span>
      )

    case 'DONE':
      return (
        <span className="task__icon task__icon--right">
          <i className="fa fa-check"></i>
        </span>
      )

    case 'FAILED':
      return (
        <span className="task__icon task__icon--right">
          <i className="fa fa-warning"></i>
        </span>
      )
    }
  }

  if (typeof onRemove === 'function') {

    return (
      <a
        href="#"
        className="task__icon task__icon--right"
        onClick={(e) => {
          e.preventDefault()
          e.stopPropagation()
          onRemove(task)
        }}
        title={ t('ADMIN_DASHBOARD_UNASSIGN_TASK', { id: task.id }) }
      ><i className="fa fa-times"></i></a>
    )
  }

  return null
}

const { show } = useContextMenu({
  id: 'task-contextmenu',
})

class Task extends React.Component {

  constructor(props) {
    super(props)

    this.onClick = this.onClick.bind(this)
    this.onDoubleClick = this.onDoubleClick.bind(this)
    this.prevent = false
  }

  // @see https://css-tricks.com/snippets/javascript/bind-different-events-to-click-and-double-click/

  onClick(e) {
    e.stopPropagation()
    const multiple = (e.ctrlKey || e.metaKey)
    this.timer = setTimeout(() => {
      if (!this.prevent) {
        const { toggleTask, task } = this.props
        toggleTask(task, multiple)
      }
      this.prevent = false
    }, 250)
  }

  onDoubleClick(e) {
    e.stopPropagation()
    clearTimeout(this.timer)
    this.prevent = true

    const { task } = this.props
    this.props.setCurrentTask(task)
  }

  render() {

    // may happen if we reschedule the task and it is improperly unlinked from tasklist in the backend
    if (this.props.task === undefined) {
      return <></>
    }

    const { color, task, selected, isVisible, date, showWeightAndVolumeUnit } = this.props

    const classNames = [
      'list-group-item',
      'list-group-item--' + task.type.toLowerCase(),
      'list-group-item--' + task.status.toLowerCase(),
      'task__draggable'
    ]

    let taskAttributes = {}
    if (task.previous) {
      taskAttributes = Object.assign(taskAttributes, { 'data-previous': task.previous })
    }
    if (task.next) {
      taskAttributes = Object.assign(taskAttributes, { 'data-next': task.next })
    }

    if (selected) {
      classNames.push('task__highlighted')
    }

    if (task.hasIncidents) {
      classNames.push('task__has-incidents')
    }

    const taskProps = {
      ...taskAttributes,
      style: {
        display: isVisible ? 'block' : 'none',
      },
      key: task['@id'],
      className: classNames.join(' '),
      'data-task-id': task['@id'],
      onDoubleClick: this.onDoubleClick,
      onClick: this.onClick,
      onContextMenu: (e) => {
        e.preventDefault()

        this.props.selectTask(task)

        show({ event: e, props: task})
      }
    }

    const taskContent = (
      <span { ...taskProps }>
        <span className="list-group-item-color" style={{ backgroundColor: color }}></span>
        <span>
          <i className={ 'task__icon task__icon--type fa fa-' + (task.type === 'PICKUP' ? 'cube' : 'arrow-down') }></i>
          {task.metadata?.rescheduled ? <i className="task__icon task__icon--type fa fa-repeat"></i> : null}
          <TaskCaption task={ task } />
          <TaskAttrs task={ task } />
          <TaskTags task={ task } />
          <TaskIconRight task={ task } onRemove={ this.props.onRemove } />
          <TaskEta
            after={ task.after }
            before={ task.before }
            date={ date } />
          <TaskComments task={ task } />
          { showWeightAndVolumeUnit ?
            (
              <div className="text-muted">
                <span>{ formatWeight(task.weight) } kg</span>
                <span className="mx-2">|</span>
                <span>{ formatVolumeUnits(getTaskVolumeUnits(task)) } VU</span>
                <span className="mx-2">|</span>
                <span>{ getTaskPackages(task) }</span>
              </div>
            )
            : null
          }
        </span>
      </span>)

    if(this.props.taskWithoutDrag) {
      return taskContent
    } else {
      return (
        <Draggable key={ task['@id'] } draggableId={ task['@id'] } index={ this.props.draggableIndex }>
          {(provided, snapshot) => {
            return (
              <div
                ref={ provided.innerRef }
                { ...provided.draggableProps }
                { ...provided.dragHandleProps }
              >
                { taskContent}
                {(snapshot.isDragging && this.props.selectedTasks.length > 1) && (
                  <div className="task-dragging-number">
                    <span>{ this.props.selectedTasks.length }</span>
                  </div>
                )}
              </div>
              )
            }}
          </Draggable>
      )
    }
  }
}

function mapStateToProps(state, ownProps) {

  let task = selectTaskById(state, ownProps.taskId)

  // may happen if we reschedule the task and it is improperly unlinked from tasklist in the backend
  if (task === undefined) {
    console.error("Could not find task at id " + ownProps.taskId)
    return { task: task}
  }

  const tasksWithColor = selectTasksWithColor(state)

  const color = Object.prototype.hasOwnProperty.call(tasksWithColor, task['@id']) ?
    tasksWithColor[task['@id']] : '#ffffff'

  const visibleTaskIds = selectVisibleTaskIds(state)
  const selectedTasks = state.selectedTasks

  const { showWeightAndVolumeUnit } = selectSettings(state)

  return {
    task: task,
    selectedTasks: selectedTasks,
    selected: -1 !== selectedTasks.indexOf(task['@id']),
    color,
    date: selectSelectedDate(state),
    isVisible: _.includes(visibleTaskIds, task['@id']),
    showWeightAndVolumeUnit: showWeightAndVolumeUnit
  }
}

function mapDispatchToProps (dispatch) {
  return {
    setCurrentTask: (task) => dispatch(setCurrentTask(task)),
    toggleTask: (task, multiple) => dispatch(toggleTask(task, multiple)),
    selectTask: (task) => dispatch(selectTask(task)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(Task))
