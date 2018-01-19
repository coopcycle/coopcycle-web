import React from 'react'
import TaskIcon from './TaskIcon'
import moment from 'moment'

moment.locale($('html').attr('lang'))

export default class extends React.Component {
  render() {

    const { task } = this.props

    const classNames = [
      'list-group-item',
      'list-group-item--' + task.type.toLowerCase(),
    ]

    return (
      <div key={ task['@id'] } className={ classNames.join(' ') } data-task-id={ task['@id'] }>
        <i style={{ fontSize: '14px' }} className={ 'fa fa-' + (task.type === 'PICKUP' ? 'arrow-up' : 'arrow-down') }></i>  
        <a><span>{ task.address.streetAddress }</span></a>
        <br />
        <span>#{ task.delivery.id }</span> <span>{ moment(task.doneAfter).format('LT') } - { moment(task.doneBefore).format('LT') }</span>
        { this.props.assigned &&
          <a href="#" className="task-remove" onClick={(e) => {
            e.preventDefault()
            this.props.onRemove(task)
          }}><i className="fa fa-times"></i></a>
        }
      </div>
    )

  }
}
