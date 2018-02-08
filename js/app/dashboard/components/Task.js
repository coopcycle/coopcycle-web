import React from 'react'
import { render } from 'react-dom'
import TaskTimeline from './TaskTimeline'
import TaskRangePicker from '../widgets/TaskRangePicker'
import moment from 'moment'

moment.locale($('html').attr('lang'))

const taskModalURL = window.AppData.Dashboard.taskModalURL

class Task extends React.Component {

  renderIconRight() {

    const { assigned, task } = this.props

    if (assigned) {
      if (task.status === 'TODO') {
        return (
          <a href="#" className="task-icon-right" onClick={(e) => {
            e.preventDefault()
            this.props.onRemove(task)
          }}><i className="fa fa-times"></i></a>
        )
      }
      if (task.status === 'DONE') {
        return (
          <span className="task-icon-right">
            <i className="fa fa-check"></i>
          </span>
        )
      }
      if (task.status === 'FAILED') {
        return (
          <span className="task-icon-right">
            <i className="fa fa-warning"></i>
          </span>
        )
      }
    }
  }

  showTaskModal(e) {
    e.preventDefault()

    const { task } = this.props

    $('#task-edit-modal')
      .load(taskModalURL.replace('__TASK_ID__', task.id), () => {

        new TaskRangePicker(document.querySelector('#task_edit_rangepicker'), [
          document.querySelector('#task_edit_doneAfter'),
          document.querySelector('#task_edit_doneBefore')
        ])

        new CoopCycle.AddressInput(document.querySelector('#task_edit_address_streetAddress'), {
          elements: {
            latitude: document.querySelector('#task_edit_address_latitude'),
            longitude: document.querySelector('#task_edit_address_longitude'),
            postalCode: document.querySelector('#task_edit_address_postalCode'),
            addressLocality: document.querySelector('#task_edit_address_addressLocality')
          }
        })

        render(
          <TaskTimeline task={ task } />,
          document.querySelector('#task_edit_history'))

        $('#task-edit-modal').modal({ show: true })
      })
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
        <i style={{ fontSize: '14px' }} className={ 'fa fa-' + (task.type === 'PICKUP' ? 'arrow-up' : 'arrow-down') }></i>  
        <a onClick={ this.showTaskModal.bind(this) }><span>{ task.address.streetAddress }</span></a>
        <br />
        { task.delivery && (
          <span>#{ task.delivery.id }</span>
        )}
        <span>{ moment(task.doneAfter).format('LT') } - { moment(task.doneBefore).format('LT') }</span>
        { this.renderIconRight() }
      </div>
    )

  }
}

export default Task
