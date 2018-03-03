import React from 'react'
import Task from './Task'

export default class extends React.Component {
  render() {
    const { group, tasks } = this.props
    return (
      <div className="panel panel-default nomargin task__draggable">
        <div className="panel-heading" role="tab">
          <h4 className="panel-title">
            <a role="button" data-toggle="collapse" href={ `#task-group-panel-${group.id}` }>
              <i className="fa fa-folder"></i> { group.name } <span className="badge">{ tasks.length }</span>
            </a>
            <a role="button" href="#" className="pull-right"
              data-toggle="modal" data-target="#task-group-modal" data-group={ group.id }>
              <i className="fa fa-trash"></i>
            </a>
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
