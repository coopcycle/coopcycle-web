import React from 'react'
import { findDOMNode } from 'react-dom'
import { connect } from 'react-redux'
import moment from 'moment'
import dragula from 'react-dragula'
import _ from 'lodash'
import Task from './Task'
import { removeTasks, saveUserTasksRequest } from '../store/actions'

moment.locale($('html').attr('lang'))

class UserPanel extends React.Component {

  constructor(props) {
    super(props)
    this.state = {
      collapsed: props.collapsed,
    }
  }

  componentDidMount() {
    this.props.onLoad(findDOMNode(this))

    const { username, collapsed } = this.props

    $('#collapse-' + username).on('shown.bs.collapse', () => {
      this.setState({ collapsed: false })
    })

    $('#collapse-' + username).on('hidden.bs.collapse', () => {
      this.setState({ collapsed: true })
    })

    if (!collapsed) {
      $('#collapse-' + username).collapse('show')
    }

    // handler to change the task order within a courier tasklist
    const container = findDOMNode(this).querySelector('.courier-task-list')
    dragula([container], {
      // You can set accepts to a method with the following signature: (el, target, source, sibling).
      // It'll be called to make sure that an element el, that came from container source,
      // can be dropped on container target before a sibling element.
      // The sibling can be null, which would mean that the element would be placed as the last element in the container.
      accepts: (el, target, source, sibling) => {

        if (el === sibling) {
          return true
        }

        const { tasks } = this.props

        const draggedTask = _.find(tasks, task => task['@id'] === el.getAttribute('data-task-id'))

        if (!draggedTask.hasOwnProperty('group')) {
          return true
        }

        const taskOrder = _.map(tasks, task => task['@id'])

        let siblingTaskIndex
        if (sibling === null) {
          siblingTaskIndex = tasks.length - 1
        } else {
          const siblingTask = _.find(tasks, task => task['@id'] === sibling.getAttribute('data-task-id'))
          siblingTaskIndex  = taskOrder.indexOf(siblingTask['@id'])
        }

        if (draggedTask.previous) {
          const previousTaskIndex = taskOrder.indexOf(draggedTask.previous)
          if (siblingTaskIndex <= previousTaskIndex) {
            return false
          }
        } else {
          const nextTask = _.find(tasks, task => task.previous === draggedTask['@id'])
          const nextTaskIndex = taskOrder.indexOf(nextTask['@id'])
          if (siblingTaskIndex >= nextTaskIndex) {
            return false
          }
        }

        return true
      }
    }).on('drop', (element, target, source) => {

      const { tasks } = this.props

      const elements = target.querySelectorAll('.list-group-item')
      const tasksOrder = _.map(elements, element => element.getAttribute('data-task-id'))

      let newTasks = tasks.slice()
      newTasks.sort((a, b) => {
        const keyA = tasksOrder.indexOf(a['@id'])
        const keyB = tasksOrder.indexOf(b['@id'])

        return keyA > keyB ? 1 : -1
      })

      this.props.saveUserTasksRequest(this.props.username, newTasks)

    })

  }

  componentDidUpdate(prevProps) {
    let taskComparator = (taskA, taskB) => taskA['@id'] === taskB['@id']

    // use a comparator to avoid infinite loop when refreshing tasks with data from server (because of task event additions)
    if (prevProps.tasks.length !== this.props.tasks.length || !_.isEqualWith(prevProps.tasks, this.props.tasks, taskComparator)) {
      this.props.saveUserTasksRequest(this.props.username, this.props.tasks)
    }
  }

  remove(taskToRemove) {

    // Check if we need to remove another linked task
    let tasksToRemove = []
    if (taskToRemove.hasOwnProperty('group')) {
      tasksToRemove = _.filter(this.props.tasks, task => task.hasOwnProperty('group') && task.group === taskToRemove.group)
    } else {
      tasksToRemove = [ taskToRemove ]
    }

    this.props.removeTasks(this.props.username, tasksToRemove)
  }

  render() {

    const { duration, distance, username, tasks } = this.props
    const { collapsed } = this.state

    tasks.sort((a, b) => {
      return a.position > b.position ? 1 : -1
    })

    const durationFormatted = moment.utc()
      .startOf('day')
      .add(duration, 'seconds')
      .format('HH:mm')

    const distanceFormatted = (distance / 1000).toFixed(2) + ' Km'

    return (
      <div className="panel panel-default nomargin">
        <div className="panel-heading">
          <h3 className="panel-title">
            <i className="fa fa-user"></i> 
            <a role="button" data-toggle="collapse" data-parent="#accordion" href={ '#collapse-' + username }>{ username }</a> 
            { collapsed && ( <i className="fa fa-caret-down"></i> ) }
            { !collapsed && ( <i className="fa fa-caret-up"></i> ) }
          </h3>
        </div>
        <div id={ 'collapse-' + username } className="panel-collapse collapse" role="tabpanel">
          { tasks.length > 0 && (
            <div className="panel-body">
              <strong>Durée</strong>  <span>{ durationFormatted }</span>
              <br />
              <strong>Distance</strong>  <span>{ distanceFormatted }</span>
            </div>
          )}
          <div className="list-group dropzone" data-username={ username }>
            <div className="list-group-item text-center dropzone-item">
              Déposez les livraisons ici
            </div>
          </div>
          <div className="list-group courier-task-list">
            { tasks.map(task => (
              <Task
                key={ task['@id'] }
                task={ task }
                assigned={ true }
                onRemove={ task => this.remove(task) }
              />
            ))}
          </div>
        </div>
      </div>
    )
  }
}

function mapStateToProps(state, ownProps) {
  return {
    tasks: state.assignedTasksByUser[ownProps.username],
    distance: state.assignedTasksByUser[ownProps.username].distance,
    duration: state.assignedTasksByUser[ownProps.username].duration,
  }
}

function mapDispatchToProps(dispatch) {
  return {
    removeTasks: (username, tasks) => { dispatch(removeTasks(username, tasks)) },
    saveUserTasksRequest: (username, tasks) => { dispatch(saveUserTasksRequest(username, tasks)) },
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(UserPanel)
