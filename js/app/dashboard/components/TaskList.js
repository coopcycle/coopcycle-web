import React from 'react'
import { findDOMNode } from 'react-dom'
import _ from 'lodash'
import Task from './Task'
import TaskGroup from './TaskGroup'

export default class extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      tasks: props.tasks || [],
    }
  }
  componentDidMount() {
    this.props.onLoad(findDOMNode(this))
  }
  add(task) {
    let { tasks } = this.state
    tasks = tasks.slice()
    tasks.push(task)
    this.setState({ tasks })
  }
  remove(task) {
    const { tasks } = this.state

    let tasksToRemove = []
    if (Array.isArray(task)) {
      task.forEach(task => tasksToRemove.push(task))
    } else {
      tasksToRemove = [ task ]
    }

    const newTasks = tasks.slice()

    _.remove(newTasks, task => _.find(tasksToRemove, taskToRemove => task['@id'] === taskToRemove['@id']))

    this.setState({ tasks: newTasks })
  }
  render() {

    const { tasks } = this.state

    // TODO
    // Right now, there are only groups
    // In the future task.delivery might be NULL
    const taskGroups = _.groupBy(tasks, task => task.delivery['@id'])

    return (
      <div className="list-group task-list">
        { _.map(taskGroups, (tasks, key) => {
          return (
            <TaskGroup key={ key } tasks={ tasks } />
          )
        })}
      </div>
    )
  }
}
