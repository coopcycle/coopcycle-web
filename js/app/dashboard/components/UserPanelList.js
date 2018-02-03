import React from 'react'
import { findDOMNode } from 'react-dom'
import UserPanel from './UserPanel'
import moment from 'moment'
import dragula from 'react-dragula'
import _ from 'lodash'

export default class extends React.Component {

  constructor(props) {
    super(props)
    this.state = {
      users: props.users || [],
      tasks: props.tasks || [],
      uncollapsed: null
    }
  }

  add(username) {
    let { users } = this.state
    const newUsers = users.slice()
    newUsers.push(username)
    this.setState({ users: newUsers, uncollapsed: username })
  }

  componentDidMount() {
    // Hide other collapsibles when a collapsible is going to be shown
    $('#accordion').on('show.bs.collapse', '.collapse', () => {
      $('#accordion').find('.collapse.in').collapse('hide')
    });
  }

  render() {

    const { map, taskLists } = this.props
    const { tasks, users } = this.state
    let { uncollapsed } = this.state

    const tasksByUser = _.groupBy(tasks, task => task.assignedTo)

    users.forEach(username => {
      if (!tasksByUser.hasOwnProperty(username)) {
        tasksByUser[username] = []
      }
    })

    if (!uncollapsed) {
      uncollapsed = _.first(_.keys(tasksByUser))
    }

    return (
      <div id="accordion">
      { _.map(tasksByUser, (tasks, username) => {

        let distance = 0
        let duration = 0
        if (taskLists.hasOwnProperty(username)) {
          distance = taskLists[username].distance
          duration = taskLists[username].duration
        }

        return (
          <UserPanel
            key={ username }
            eventEmitter={ this.props.eventEmitter }
            username={ username }
            tasks={ tasks }
            distance={ distance }
            duration={ duration }
            map={ map }
            collapsed={ uncollapsed !== username }
            onShow={() => {}}
            onHide={() => {}}
            onLoad={ (component, element) => this.props.onLoad(component, element.querySelector('.panel .list-group')) }
            onTaskListChange={ this.props.onTaskListChange }
            onRemove={ this.props.onRemove }
            save={ this.props.save } />
          )
      })}
      </div>
    )
  }
}
