import React from 'react'
import { withTranslation } from 'react-i18next'
import Popconfirm from 'antd/lib/popconfirm'

import Task from './Task'

class TaskGroup extends React.Component {

  renderTags() {
    const { group } = this.props

    return (
      <span className="task__tags">
        { group.tags.map(tag => (
          <i key={ tag.slug } className="fa fa-circle" style={{ color: tag.color }}></i>
        )) }
      </span>
    )
  }

  render() {
    const { group, tasks } = this.props

    tasks.sort((a, b) => {
      return a.id > b.id ? 1 : -1
    })

    return (
      <div className="panel panel-default nomargin task__draggable">
        <div className="panel-heading" role="tab">
          <h4 className="panel-title">
            <i className="fa fa-folder"></i> <a role="button" data-toggle="collapse" href={ `#task-group-panel-${group.id}` }>
              { group.name } <span className="badge">{ tasks.length }</span>
            </a>
            <Popconfirm
              placement="left"
              title={ this.props.t('ADMIN_DASHBOARD_DELETE_GROUP_CONFIRM') }
              onConfirm={ this.props.onConfirmDelete }
              okText={ this.props.t('CROPPIE_CONFIRM') }
              cancelText={ this.props.t('ADMIN_DASHBOARD_CANCEL') }
              >
              <a role="button" href="#" className="pull-right"
                onClick={ e => e.preventDefault() }>
                <i className="fa fa-trash"></i>
              </a>
            </Popconfirm>
            { this.renderTags() }
          </h4>
        </div>
        <div id={ `task-group-panel-${group.id}` } className="panel-collapse collapse" role="tabpanel">
          <ul className="list-group">
            { tasks.map(task => {
              return (
                <Task
                  key={ task['@id'] }
                  task={ task }
                  assigned={ false }
                />
              )
            })}
          </ul>
        </div>
      </div>
    )
  }
}

export default withTranslation()(TaskGroup)
