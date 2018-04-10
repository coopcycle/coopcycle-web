import React from 'react'
import { render } from 'react-dom'
import moment from 'moment'

moment.locale($('html').attr('lang'))

const taskModalURL = window.AppData.Dashboard.taskModalURL

class Task extends React.Component {

  constructor(props) {
    super(props)

    this.onClick = this.onClick.bind(this)
  }

  renderStatusIcon() {

    const { assigned, task } = this.props

    if (assigned) {
      if (task.status === 'TODO') {
        return (
          <a
            href="#"
            className="task__icon task__icon--right"
            onClick={(e) => {
              e.preventDefault()
              this.props.onRemove(task)
            }}
            data-toggle="tooltip" data-placement="right" title="Désassigner"
          ><i className="fa fa-times"></i></a>
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

  onClick () {
    const { highlightTask, task } = this.props
    highlightTask(task)
  }

  renderLinkedIcon() {

    const { assigned, task } = this.props
    const classNames = ['task__icon']
    classNames.push(assigned ? 'task__icon--left' : 'task__icon--right')

    if (task.hasOwnProperty('link')) {
      return (
        <span className={ classNames.join(' ') }><i className="fa fa-exchange"></i></span>
      )
    }
  }

  renderTags() {
    const { task } = this.props

    return (
      <span className="task__tags">
      { task.tags.map(tag => (
        <i key={ tag.slug } className="fa fa-circle" style={{ color: tag.color }}></i>
      )) }
      </span>
    )
  }

  showTaskModal(e) {
    e.preventDefault()
    e.stopPropagation()

    const { task } = this.props

    $('#task-edit-modal')
      .load(taskModalURL.replace('__TASK_ID__', task.id), () => $('#task-edit-modal').modal({ show: true }))
  }

  render() {

    const { task, highlightedTask } = this.props

    const classNames = [
      'list-group-item',
      'list-group-item--' + task.type.toLowerCase(),
      'list-group-item--' + task.status.toLowerCase(),
      'task__draggable'
    ]

    let taskAttributes = {}
    if (task.hasOwnProperty('link')) {
      taskAttributes = Object.assign(taskAttributes, { 'data-link': task.link })
    }

    if (task === highlightedTask) {
      classNames.push('task__highlighted')
    }

    return (
      <div key={ task['@id'] } className={ classNames.join(' ') } data-task-id={ task['@id'] } { ...taskAttributes } onClick={this.onClick}>
        <div>
          <i className={ 'task__icon task__icon--type fa fa-' + (task.type === 'PICKUP' ? 'cube' : 'arrow-down') }></i>
          <span>Tâche #{/([\d]+)/.exec(task['@id'])[0]}</span>{ task.address.name && (<span> - { task.address.name }</span>)}
          { this.renderTags() }
          &nbsp;&nbsp;
          <a onClick={ this.showTaskModal.bind(this) }>
            <i className="fa fa-pencil task__icon--edit"></i>
          </a>
        </div>
        <div>
          <span>{ task.address.name || task.address.streetAddress }</span>
        </div>
        <div>
          <span>{ moment(task.doneAfter).format('LT') } - { moment(task.doneBefore).format('LT') }</span>
        </div>
        <div>
          { this.renderLinkedIcon() }
        </div>
        {this.renderStatusIcon()}
      </div>
    )

  }
}

export default Task
