import React, { Component } from 'react'
import moment from 'moment'

import i18n from '../../i18n'
import { addressAsText } from '../utils'

export default class extends Component {

  constructor (props) {
    super(props)
    this.state = {
      task: this.props.task
    }
  }

  updateTask(task) {
    this.setState({ task })
  }

  render() {

    const { task } = this.state

    return (
      <div>
        <div>
          <span>
            { i18n.t('ADMIN_DASHBOARD_TASK_CAPTION_SHORT', { id: task.id }) }
          </span>
          <a className="task__edit" onClick={ this.props.onEditClick }>
            <i className="fa fa-pencil"></i>
          </a>
        </div>
        <div>
          { addressAsText(task.address) }
        </div>
        <div>
          { i18n.t('ADMIN_DASHBOARD_TASK_TIME_RANGE', {
            after: moment(task.doneAfter).format('LT'),
            before: moment(task.doneBefore).format('LT')
          }) }
        </div>
        { task.assignedTo && (
          <div>
            { i18n.t('ADMIN_DASHBOARD_TASK_ASSIGNED_TO', { username: task.assignedTo }) }
          </div>
        )}
        <div>
          { task.tags.map((item) => (
            <span key={ `${item.slug}-${item.color}` } style={{ color: '#fff', padding: '2px', backgroundColor: item.color }}>
              { item.name }
            </span>
          ))}
        </div>
      </div>
    )
  }
}
