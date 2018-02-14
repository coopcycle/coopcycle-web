import React from 'react'
import { connect } from 'react-redux'
import dragula from 'dragula'
import { assignTasks, updateTask } from './store/actions'
import TaskList from './components/TaskList'
import UserPanelList from './components/UserPanelList'

let inDraggingUnassignedTask

const drake = dragula({
  copy: true,
  copySortSource: false,
  revertOnSpill: true,
  accepts: (el, target, source, sibling) => target !== source
})
.on('cloned', function (clone, original) {
  // fired when we start dragging from unassigned tasks
  inDraggingUnassignedTask = original
  if ($(original).hasClass('list-group-item')) {
    $(original).addClass('disabled')
  } else {
    $(original).find('.list-group-item').addClass('disabled')
  }
}).on('dragend', function (el) {
  if ($(inDraggingUnassignedTask).hasClass('list-group-item')) {
    $(inDraggingUnassignedTask).removeClass('disabled')
  } else {
    $(inDraggingUnassignedTask).find('.list-group-item').removeClass('disabled')
  }
}).on('over', function (el, container, source) {
  if ($(container).hasClass('dropzone')) {
    $(container).addClass('dropzone--over')
  }
}).on('out', function (el, container, source) {
  if ($(container).hasClass('dropzone')) {
    $(container).removeClass('dropzone--over')
  }
})

/**
 * Code to handle drag and drop from unassigned tasks to assigned
 */
function configureOnDrop(allTasks, assignTasks) {

  drake
    .on('drop', function(element, target, source) {

      const username = $(target).data('username')
      let tasks = []

      if ($(element).data('task-group') === true) {
        tasks = $(element)
          .children()
          .map((index, el) => $(el).data('task-id'))
          .map((index, taskID) => _.find(allTasks, task => task['@id'] === taskID))
          .toArray()
      } else {
        const task = _.find(allTasks, task => task['@id'] === $(element).data('task-id'))
        tasks.push(task)
      }

      assignTasks(username, tasks)

      $(target).removeClass('dropzone--loading')
      element.remove()
    })

}

class DashboardApp extends React.Component {

  componentDidMount() {
    this.props.socket.on('task:done', task => this.props.updateTask(task))
    this.props.socket.on('task:failed', task => this.props.updateTask(task))
    configureOnDrop(this.props.allTasks, this.props.assignTasks)
  }

  render () {
    return (
      <div className="dashboard__aside-container">
        <TaskList
          onLoad={ el => drake.containers.push(el) } />
        <UserPanelList
          couriersList={ window.AppData.Dashboard.couriersList }
          onLoad={ el => drake.containers.push(el) } />
      </div>
    )
  }
}

function mapStateToProps (state) {
  return {
    allTasks: state.allTasks
  }
}

function mapDispatchToProps (dispatch) {
  return {
    assignTasks: (username, tasks) => { dispatch(assignTasks(username, tasks)) },
    updateTask: (task) => { dispatch(updateTask(task)) }
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(DashboardApp)
