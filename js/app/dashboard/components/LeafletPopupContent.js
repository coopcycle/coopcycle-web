import React, { Component } from 'react'
import moment from 'moment'
import _ from 'lodash'

import i18n from '../../i18n'
import { addressAsText } from '../utils'
import Avatar from '../../components/Avatar'

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

    const name = i18n.t('ADMIN_DASHBOARD_ORDERS_ORDER')

    return (
      <div className="pt-2">
        <header className="d-flex justify-content-between align-items-center mb-2">
          <strong>
            { i18n.t('ADMIN_DASHBOARD_TASK_CAPTION_SHORT', { id: task.id }) }{ !_.isEmpty(task.metadata.order_number) && (' | ' + name + ' ' + task.metadata.order_number) }
          </strong>
          <span>
            <a className="task__edit" onClick={ () => this.props.onEditClick(task) }>
              { task.isAssigned && (
                <Avatar username={ task.assignedTo } />
              )}
              <i className="fa fa-lg fa-pencil ml-2"></i>
            </a>
          </span>
        </header>
        <div>
          { addressAsText(task.address) }
        </div>
        <div>
          { i18n.t('ADMIN_DASHBOARD_TASK_TIME_RANGE', {
            after: moment(task.doneAfter).format('LT'),
            before: moment(task.doneBefore).format('LT')
          }) }
        </div>
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
