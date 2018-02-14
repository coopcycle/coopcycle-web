import React from 'react'
import { render } from 'react-dom'
import TaskTimeline from './TaskTimeline'
import TaskRangePicker from '../../widgets/TaskRangePicker'
import moment from 'moment'

moment.locale($('html').attr('lang'))

const taskModalURL = window.AppData.Dashboard.taskModalURL

class Task extends React.Component {

  renderIconRight() {

    const { assigned, task } = this.props

    if (assigned) {
      if (task.status === 'TODO') {
        return (
          <a href="#" className="task__icon task__icon--right" onClick={(e) => {
            e.preventDefault()
            this.props.onRemove(task)
          }}><i className="fa fa-times"></i></a>
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

  renderIconLeft() {

    const { assigned, task } = this.props
    const classNames = ['task__icon']
    classNames.push(assigned ? 'task__icon--left' : 'task__icon--right')

    if (task.hasOwnProperty('group')) {
      return (
        <span className={ classNames.join(' ') }><i className="fa fa-exchange"></i></span>
      )
    }
  }

  showTaskModal(e) {
    e.preventDefault()

    const { task } = this.props

    $('#task-edit-modal')
      .load(taskModalURL.replace('__TASK_ID__', task.id), () => $('#task-edit-modal').modal({ show: true }))
  }

  render() {

    const { task } = this.props

    const classNames = [
      'list-group-item',
      'list-group-item--' + task.type.toLowerCase(),
      'list-group-item--' + task.status.toLowerCase(),
    ]

    return (
      <div key={ task['@id'] } className={ classNames.join(' ') } data-task-id={ task['@id'] }>
        <i style={{ fontSize: '14px' }} className={ 'fa fa-' + (task.type === 'PICKUP' ? 'cube' : 'arrow-down') }></i>  
        <a className="task__streetAddress" onClick={ this.showTaskModal.bind(this) }><span>{ task.address.streetAddress }</span></a>
        <br />
        <span>{ moment(task.doneAfter).format('LT') } - { moment(task.doneBefore).format('LT') }</span>
        { this.renderIconLeft() }
        { this.renderIconRight() }
      </div>
    )

  }
}

export default Task
