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

  onClick(e) {
    const multiple = (e.ctrlKey || e.metaKey)
    const { toggleTask, task } = this.props
    toggleTask(task, multiple)
  }

  renderLinkedIcon() {

    const { assigned, task } = this.props
    const classNames = ['task__icon']
    classNames.push(assigned ? 'task__icon--left' : 'task__icon--right')

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

    const { task, selected } = this.props

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

    if (selected) {
      classNames.push('task__highlighted')
    }

    return (
      <span
        style={{display: 'block', borderLeft: '6px solid ' + task.deliveryColor}}
        key={task['@id']}
        className={classNames.join(' ')}
        data-task-id={task['@id']}
        {...taskAttributes}
        onClick={this.onClick}>
          <i className={ 'task__icon task__icon--type fa fa-' + (task.type === 'PICKUP' ? 'cube' : 'arrow-down') }></i>
          #{/([\d]+)/.exec(task['@id'])[0]} - { task.address.name || task.address.streetAddress } avant { moment(task.doneBefore).format('HH[h]mm') }
          { this.renderTags() }
          &nbsp;
          <a className="task__edit" onClick={ this.showTaskModal.bind(this) }>
            <i className="fa fa-pencil"></i>
          </a>
          {this.renderLinkedIcon()}
          {this.renderStatusIcon()}
      </span>

    )

  }
}

export default Task
