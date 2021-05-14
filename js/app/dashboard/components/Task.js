import React from 'react'
import { connect } from 'react-redux'
import { withTranslation, useTranslation } from 'react-i18next'
import moment from 'moment'
import { useContextMenu } from 'react-contexify'
import _ from 'lodash'

import { setCurrentTask, toggleTask, selectTask } from '../redux/actions'
import { selectVisibleTaskIds } from '../redux/selectors'
import { selectSelectedDate, selectTasksWithColor } from '../../coopcycle-frontend-js/logistics/redux'

import { addressAsText } from '../utils'
import TaskEta from './TaskEta'

moment.locale($('html').attr('lang'))

const TaskCaption = ({ task }) => {

  const { t } = useTranslation()

  return (
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

const TaskIconRight = ({ task, assigned, onRemove }) => {

  const { t } = useTranslation()

  if (assigned) {
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

  return null
}

const { show } = useContextMenu({
  id: 'dashboard',
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

  render() {

    const { color, task, selected, isVisible, date, assigned } = this.props

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
        show(e, {
          props: { task }
        })
      }
    }

    return (
      <span { ...taskProps }>
        <span className="list-group-item-color" style={{ backgroundColor: color }}></span>
        <span>
          <i className={ 'task__icon task__icon--type fa fa-' + (task.type === 'PICKUP' ? 'cube' : 'arrow-down') }></i>
          <TaskCaption task={ task } />
          <TaskAttrs task={ task } />
          <TaskTags task={ task } />
          <TaskIconRight task={ task } assigned={ assigned } onRemove={ this.props.onRemove } />
          <TaskEta
            after={ task.after }
            before={ task.before }
            date={ date } />
        </span>
      </span>
    )

  }
}

function mapStateToProps(state, ownProps) {

  const tasksWithColor = selectTasksWithColor(state)

  const color = Object.prototype.hasOwnProperty.call(tasksWithColor, ownProps.task['@id']) ?
    tasksWithColor[ownProps.task['@id']] : '#ffffff'

  const visibleTaskIds = selectVisibleTaskIds(state)

  return {
    selected: -1 !== state.selectedTasks.indexOf(ownProps.task['@id']),
    color,
    date: selectSelectedDate(state),
    isVisible: _.includes(visibleTaskIds, ownProps.task['@id']),
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
