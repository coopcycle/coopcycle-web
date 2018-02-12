import React from 'react'
import { findDOMNode } from 'react-dom'
import _ from 'lodash'
import Task from './Task'
import TaskGroup from './TaskGroup'
import { connect } from 'react-redux'


class TaskList extends React.Component {

  componentDidMount() {
    this.props.onLoad(findDOMNode(document.getElementById('task-list')))
  }

  render() {

    const { unassignedTasks } = this.props,
          standaloneTasks = _.filter(unassignedTasks, task => task.delivery === null),
          groupedTasks = _.filter(unassignedTasks, task => task.delivery !== null),
          taskGroups = _.groupBy(groupedTasks, task => task.delivery['@id'])

    return (
      <div className="dashboard__panel">
        <h4>
          <span>{ window.AppData.Dashboard.i18n['Unassigned'] }</span>
          <a href="#" className="pull-right" onClick={ e => {
            e.preventDefault();
            $('#task-modal').modal('show')
          }}>
            <i className="fa fa-plus"></i>
          </a>
        </h4>
        <div className="dashboard__panel__scroll">
          <div className="list-group" id="task-list">
            { _.map(taskGroups, (tasks, key) => {
              return (
                <TaskGroup key={ key } tasks={ tasks } />
              )
            })}
            { _.map(standaloneTasks, (task, key) => {
              return (
                <Task
                  key={ key }
                  task={ task }
                />
              )
            })}
          </div>
        </div>
      </div>
    )
  }
}

function mapStateToProps (state) {
  return {
    unassignedTasks: state.unassignedTasks
  }
}

export default connect(mapStateToProps)(TaskList)
