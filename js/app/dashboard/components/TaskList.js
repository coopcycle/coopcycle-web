import React from 'react'
import { findDOMNode } from 'react-dom'
import { connect } from 'react-redux'
import moment from 'moment'
import dragula from 'react-dragula'
import _ from 'lodash'
import Task from './Task'
import { removeTasks, modifyTaskList, togglePolyline, highlightTask } from '../store/actions'

moment.locale($('html').attr('lang'))

class TaskList extends React.Component {

  componentDidMount() {
    this.props.taskListDidMount(this)

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
    const currentNode = findDOMNode(this),
      container = currentNode.querySelector('.courier-task-list'),
      scrollableWrapper = currentNode.querySelector('#scrollable-wrapper')
    let drake = dragula([container], {
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

        if (!draggedTask.hasOwnProperty('link')) {
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

      this.props.modifyTaskList(this.props.username, newTasks)

    })

    let autoScroll = require('dom-autoscroller')
    autoScroll([
        scrollableWrapper
      ],{
        margin: 20,
        maxSpeed: 5,
        scrollWhenOutside: true,
        autoScroll: function(){
          //Only scroll when the pointer is down, and there is a child being dragged.
          return drake.dragging
        }
    })

  }

  componentDidUpdate(prevProps) {
    let taskComparator = (taskA, taskB) => taskA['@id'] === taskB['@id']

    // use a comparator to avoid infinite loop when refreshing tasks with data from server (because of task event additions)
    if (prevProps.tasks.length !== this.props.tasks.length || !_.isEqualWith(prevProps.tasks, this.props.tasks, taskComparator)) {
      this.props.modifyTaskList(this.props.username, this.props.tasks)
    }
  }

  remove(taskToRemove) {

    // Check if we need to remove another linked task
    let tasksToRemove = []
    if (taskToRemove.hasOwnProperty('link')) {
      tasksToRemove = _.filter(this.props.tasks, task => task.hasOwnProperty('link') && task.link === taskToRemove.link)
    } else {
      tasksToRemove = [ taskToRemove ]
    }

    this.props.removeTasks(this.props.username, tasksToRemove)
  }

  render() {

    const {
      duration,
      distance,
      username,
      polylineEnabled,
      showFinishedTasks,
      selectedTags,
      showUntaggedTasks,
      highlightedTask
    } = this.props
    let { tasks } = this.props

    tasks = _.orderBy(tasks, ['position', 'id'])

    if (!showFinishedTasks) {
      tasks = _.filter(tasks, (task) => { return task.status === 'TODO' })
    }

    // tag filtering - task should have at least one of the selected tags
    tasks = _.filter(tasks, (task) =>
      (task.tags.length > 0 &&_.intersectionBy(task.tags, selectedTags, 'name').length > 0) ||
      (task.tags.length === 0 && showUntaggedTasks)
    )

    const durationFormatted = moment.utc()
      .startOf('day')
      .add(duration, 'seconds')
      .format('HH:mm')

    const distanceFormatted = (distance / 1000).toFixed(2) + ' Km'

    const polylineClassNames = ['pull-right', 'taskList__summary-polyline']
    if (polylineEnabled) {
      polylineClassNames.push('taskList__summary-polyline--enabled')
    }

    return (
      <div className="panel panel-default nomargin">
        <div className="panel-heading">
          <h3 className="panel-title">
            <img src={ window.AppData.Dashboard.avatarURL.replace('__USERNAME__', username) } width="20" height="20" /> 
            <a role="button">{ username } <span className="badge">{ tasks.length }</span></a> 
          </h3>
        </div>
        <div role="tabpanel">
          { tasks.length > 0 && (
            <div className="panel-body taskList__summary">
              <strong>Durée</strong>  <span>{ durationFormatted }</span> - <strong>Distance</strong>  <span>{ distanceFormatted }</span>
              <a role="button" className={ polylineClassNames.join(' ') } onClick={ e => this.props.togglePolyline(username) }>
                <i className="fa fa-map fa-2x"></i>
              </a>
            </div>
          )}
          <div className="list-group dropzone" data-username={ username }>
            <div className="list-group-item text-center dropzone-item">
              Déposez les livraisons ici
            </div>
          </div>
          <div id="scrollable-wrapper" style={{overflow: 'auto', maxHeight: '250px'}}>
            <div className="list-group courier-task-list">
              { tasks.map(task => (
                <Task
                  key={ task['@id'] }
                  task={ task }
                  assigned={ true }
                  onRemove={ task => this.remove(task) }
                  highlightedTask={ highlightedTask }
                  highlightTask={ this.props.highlightTask }
                />
              ))}
            </div>
          </div>
        </div>
      </div>
    )
  }
}

function mapStateToProps(state, ownProps) {
  return {
    polylineEnabled: state.polylineEnabled[ownProps.username],
    tasks: ownProps.items,
    distance: ownProps.distance,
    duration: ownProps.duration,
    showFinishedTasks: state.taskFinishedFilter,
    selectedTags: state.tagsFilter.selectedTagsList,
    showUntaggedTasks: state.tagsFilter.showUntaggedTasks,
    highlightedTask: state.highlightedTask
  }
}

function mapDispatchToProps(dispatch) {
  return {
    removeTasks: (username, tasks) => { dispatch(removeTasks(username, tasks)) },
    modifyTaskList: (username, tasks) => { dispatch(modifyTaskList(username, tasks)) },
    togglePolyline: (username) => { dispatch(togglePolyline(username)) },
    highlightTask: (task) => { dispatch(highlightTask(task)) }
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(TaskList)
