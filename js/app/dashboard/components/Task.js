import React from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import moment from 'moment'
import { ContextMenuTrigger } from 'react-contextmenu'
import _ from 'lodash'

import { setCurrentTask, toggleTask, selectTask } from '../redux/actions'
import { selectIsVisibleTask } from '../redux/selectors'
import { selectSelectedDate, selectTasksWithColor } from '../../coopcycle-frontend-js/logistics/redux'

import { addressAsText } from '../utils'
import TaskEta from './TaskEta'

moment.locale($('html').attr('lang'))

const TaskCaption = ({ task, t }) => (
  <span>
    <span className="mr-1">#{ task.id }</span>
    { (task.orgName && !_.isEmpty(task.orgName)) && (
      <span>
        <span className="font-weight-bold">{ task.orgName }</span>
        <span className="mx-1">›</span>
      </span>
    ) }
    { t('ADMIN_DASHBOARD_TASK_CAPTION', {
      address: addressAsText(task.address),
      date: moment(task.before).format('LT')
    }) }
  </span>
)

class Task extends React.Component {

  constructor(props) {
    super(props)

    this.onClick = this.onClick.bind(this)
    this.onDoubleClick = this.onDoubleClick.bind(this)
    this.prevent = false
  }

  renderIconRight() {

    const { assigned, task } = this.props

    if (assigned) {
      if (task.status === 'TODO') {
        return (
          <a
            href="#"
            className="task__icon task__icon--right"
            onClick={(e) => {
              e.preventDefault()
              e.stopPropagation()
              this.props.onRemove(task)
            }}
            title={ this.props.t('ADMIN_DASHBOARD_UNASSIGN_TASK', { id: task.id }) }
          ><i className="fa fa-times"></i></a>
        )
      }
      if (task.status === 'DOING') {
        return (
          <span className="task__icon task__icon--right">
            <i className="fa fa-play"></i>
          </span>
        )
      }
      if (task.status === 'DONE') {
        return (
          <span className="task__icon task__icon--right">
            <i className="fa fa-check"></i>
          </span>
        )
      }
      if (task.status === 'FAILED') {
        return (
          <span className="task__icon task__icon--right">
            <i className="fa fa-warning"></i>
          </span>
        )
      }
    }
  }

  // @see https://css-tricks.com/snippets/javascript/bind-different-events-to-click-and-double-click/

  onClick(e) {
    const multiple = (e.ctrlKey || e.metaKey)
    this.timer = setTimeout(() => {
      if (!this.prevent) {
        const { toggleTask, task } = this.props
        toggleTask(task, multiple)
      }
      this.prevent = false
    }, 250)
  }

  onDoubleClick() {
    clearTimeout(this.timer)
    this.prevent = true

    const { task } = this.props
    this.props.setCurrentTask(task)
  }

  renderAttrs() {
    const { task } = this.props

    if (task.images && task.images.length > 0) {
      return (
        <span className="task__attrs">
          <i className="fa fa-camera"></i>
        </span>
      )
    }
  }

  renderTags() {
    const { task } = this.props

    if (task.tags.length > 0) {
      return (
        <span className="task__tags">
          { task.tags.map(tag => (
            <i key={ tag.slug } className="fa fa-circle" style={{ color: tag.color }}></i>
          )) }
        </span>
      )
    }
  }

  renderEtaProgress() {
    return (
      <TaskEta date={ this.props.date } task={ this.props.task } />
    )
  }

  render() {

    const { color, task, selected, isVisible } = this.props

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

    const contextMenuTriggerAttrs = {
      ...taskAttributes,
      style: {
        display: isVisible ? 'block' : 'none',
        borderLeft: `6px solid ${color}`
      },
      key: task['@id'],
      className: classNames.join(' '),
      'data-task-id': task['@id'],
      onDoubleClick: this.onDoubleClick,
      onClick: this.onClick,
      onContextMenu: () => this.props.selectTask(task)
    }

    // Don't return the task object directly, to avoid stripping the "id" prop
    const collect = (props) => ({ task: props.task })

    return (
      <ContextMenuTrigger renderTag="span" id="dashboard"
        task={ task }
        collect={ collect }
        attributes={ contextMenuTriggerAttrs }>
        <i className={ 'task__icon task__icon--type fa fa-' + (task.type === 'PICKUP' ? 'cube' : 'arrow-down') }></i>
        <TaskCaption task={ task } t={ this.props.t } />
        { this.renderAttrs() }
        { this.renderTags() }
        { this.renderIconRight() }
        { this.renderEtaProgress() }
      </ContextMenuTrigger>
    )

  }
}

function mapStateToProps(state, ownProps) {

  const tasksWithColor = selectTasksWithColor(state)

  const color = Object.prototype.hasOwnProperty.call(tasksWithColor, ownProps.task['@id']) ?
    tasksWithColor[ownProps.task['@id']] : '#ffffff'

  return {
    selected: -1 !== state.selectedTasks.indexOf(ownProps.task),
    color,
    date: selectSelectedDate(state),
    isVisible: selectIsVisibleTask({
      task: ownProps.task,
      filters: state.filters,
      date: selectSelectedDate(state),
    }),
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
