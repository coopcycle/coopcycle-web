import React from 'react'
import { findDOMNode } from 'react-dom'
import { connect } from 'react-redux'
import dragula from 'dragula'
import _ from 'lodash'

import { assignTasks, updateTask, drakeDrag, drakeDragEnd } from './store/actions'
import UnassignedTasks from './components/UnassignedTasks'
import TaskLists from './components/TaskLists'

const drake = dragula({
  copy: true,
  copySortSource: false,
  revertOnSpill: true,
  accepts: (el, target, source, sibling) => target !== source
})

/**
 * Code to handle drag and drop from unassigned tasks to assigned
 */

function onTaskDrop(allTasks, assignTasks, element, target, source) {

  const username = $(target).data('username')
  const isTask = element.hasAttribute('data-task-id')

  let tasks = []

  if (isTask) { // This is a single task

    const task = _.find(allTasks, task => task['@id'] === element.getAttribute('data-task-id'))

    // FIXME
    // Make it work when more than 2 tasks are linked together
    if (task.previous) {
      tasks = [ _.find(allTasks, t => t['@id'] === task.previous), task ]
    } else if (task.next) {
      tasks = [ task, _.find(allTasks, t => t['@id'] === task.next) ]
    } else {
      tasks = [ task ]
    }

  } else { // This is a task group
    const elements = Array.from(element.querySelectorAll('[data-task-id]'))
    tasks = elements.map(el => _.find(allTasks, task => task['@id'] === el.getAttribute('data-task-id')))
  }

  assignTasks(username, tasks)

  $(target).removeClass('dropzone--loading')

  // Remove cloned element from dropzone
  element.remove()
}

function configureDrag(drakeDrag) {
  drake
    .on('drag', function(el, source) {
      let elements = [ el ]

      // FIXME
      // Make it work when more than 2 tasks are linked together
      if (el.hasAttribute('data-previous') || el.hasAttribute('data-next')) {
        const siblings = Array.from(el.parentNode.childNodes)
        const linkedElements = _.filter(siblings, sibling =>
          sibling.getAttribute('data-task-id') === (el.getAttribute('data-previous') || el.getAttribute('data-next')))
        elements = elements.concat(linkedElements)
      }

      elements.forEach(el => el.classList.add('task__draggable--dragging'))

      drakeDrag()
    })
    .on('cloned', function (clone, original) {
      clone.classList.remove('task__draggable--dragging')
    })
    .on('over', function (el, container, source) {
      if ($(container).hasClass('dropzone')) {
        $(container).addClass('dropzone--over')
      }
    })
    .on('out', function (el, container, source) {
      if ($(container).hasClass('dropzone')) {
        $(container).removeClass('dropzone--over')
      }
    })
}

function configureDragEnd(unassignedTasksContainer, drakeDragEnd) {
  drake
    .off('dragend')
    .on('dragend', function (el) {
      Array.from(unassignedTasksContainer.querySelectorAll('.task__draggable--dragging'))
        .forEach(el => el.classList.remove('task__draggable--dragging'))
      drakeDragEnd()
    })
}

function configureDrop(allTasks, assignTasks) {
  drake
    .off('drop')
    .on('drop', onTaskDrop.bind(null, allTasks, assignTasks))
}

class DashboardApp extends React.Component {

  componentDidMount() {

    const unassignedTasksContainer = findDOMNode(this.refs.unassignedTasks).querySelector('.list-group')
    drake.containers.push(unassignedTasksContainer)

    configureDrag(this.props.drakeDrag)
    configureDragEnd(unassignedTasksContainer, this.props.drakeDragEnd)
    configureDrop(this.props.allTasks, this.props.assignTasks)

    // This event is trigerred when the task modal is submitted successfully
    $(document).on('task.form.success', '#task-edit-modal', (e) => {
      const { task } = e
      this.props.updateTask(task)
    })

  }

  componentDidUpdate(prevProps, prevState) {
    if (this.props.allTasks !== prevProps.allTasks) {
      configureDrop(this.props.allTasks, this.props.assignTasks)
    }
  }

  render () {
    return (
      <div className="dashboard__aside-container">
        <UnassignedTasks ref="unassignedTasks" />
        <TaskLists
          couriersList={ window.AppData.Dashboard.couriersList }
          ref="taskLists"
          taskListDidMount={ taskListComponent =>
            drake.containers.push(findDOMNode(taskListComponent).querySelector('.panel .list-group'))
          }
        />
      </div>
    )
  }
}

function mapStateToProps(state) {
  return {
    allTasks: state.allTasks
  }
}

function mapDispatchToProps (dispatch) {
  return {
    assignTasks: (username, tasks) => { dispatch(assignTasks(username, tasks)) },
    updateTask: (task) => { dispatch(updateTask(task)) },
    drakeDrag: () => dispatch(drakeDrag()),
    drakeDragEnd: () => dispatch(drakeDragEnd()),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(DashboardApp)
