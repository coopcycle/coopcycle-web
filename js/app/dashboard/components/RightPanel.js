import React from 'react'
import { connect } from 'react-redux'
import { DragDropContext } from 'react-beautiful-dnd'
import _ from 'lodash'
import Split from 'react-split'

import { ToastContainer } from 'react-toastify'
import 'react-toastify/dist/ReactToastify.css'
import { selectAllTasks, selectTaskLists, selectSelectedDate } from '../../coopcycle-frontend-js/dispatch/redux'

import {
  toggleSearch,
  closeSearch,
  modifyTaskList,
  clearSelectedTasks,
  createTaskList } from '../redux/actions'
import UnassignedTasks from './UnassignedTasks'
import TaskLists from './TaskLists'
import ContextMenu from './ContextMenu'
import SearchPanel from './SearchPanel'

class DashboardApp extends React.Component {

  componentDidMount() {
    window.addEventListener('keydown', e => {
      const isCtrl = (e.ctrlKey || e.metaKey)
      if (e.keyCode === 114 || (isCtrl && e.keyCode === 70)) {
        if (!this.props.searchIsOn) {
          e.preventDefault()
          this.props.toggleSearch()
        }
      }
      if (e.keyCode === 27) {
        this.props.closeSearch()
      }
    })
  }

  render () {
    return (
      <div className="dashboard__aside-container">
        <DragDropContext
          // https://github.com/atlassian/react-beautiful-dnd/blob/master/docs/patterns/multi-drag.md
          onDragStart={ result => {

            // If the user is starting to drag something that is not selected then we need to clear the selection.
            // https://github.com/atlassian/react-beautiful-dnd/blob/master/docs/patterns/multi-drag.md#dragging
            const isDraggableSelected =
              !!_.find(this.props.selectedTasks, t => t['@id'] === result.draggableId)

            if (!isDraggableSelected) {
              this.props.clearSelectedTasks()
            }

          }}
          onDragEnd={ result => {

            // dropped nowhere
            if (!result.destination) {
              return;
            }

            const source = result.source;
            const destination = result.destination;

            // reodered inside the unassigned list, do nothing
            if (
              source.droppableId === destination.droppableId &&
              source.droppableId === 'unassigned'
            ) {
              return;
            }

            // did not move anywhere - can bail early
            if (
              source.droppableId === destination.droppableId &&
              source.index === destination.index
            ) {
              return;
            }

            // cannot unassign by drag'n'drop atm
            if (source.droppableId.startsWith('assigned:') && destination.droppableId === 'unassigned') {
              return
            }

            const username = destination.droppableId.replace('assigned:', '')
            const taskList = _.find(this.props.taskLists, tl => tl.username === username)
            const newTasks = [ ...taskList.items ]

            if (this.props.selectedTasks.length > 1) {

              // FIXME Manage linked tasks
              // FIXME
              // The tasks are dropped in the order they were selected
              // Instead, we should respect the order of the unassigned tasks

              Array.prototype.splice.apply(newTasks,
                Array.prototype.concat([ result.destination.index, 0 ], this.props.selectedTasks))

            } else if (result.draggableId.startsWith('group:')) {

              const groupEl = document.querySelector(`[data-rbd-draggable-id="${result.draggableId}"]`)

              const tasksFromGroup = Array
                .from(groupEl.querySelectorAll('[data-task-id]'))
                .map(el => _.find(this.props.allTasks, t => t['@id'] === el.getAttribute('data-task-id')))

              Array.prototype.splice.apply(newTasks,
                Array.prototype.concat([ result.destination.index, 0 ], tasksFromGroup))

            } else {

              // Reorder inside same list
              if (source.droppableId === destination.droppableId) {
                const [ removed ] = newTasks.splice(result.source.index, 1);
                newTasks.splice(result.destination.index, 0, removed)
              } else {

                const task = _.find(this.props.allTasks, t => t['@id'] === result.draggableId)

                newTasks.splice(result.destination.index, 0, task)

                if (task && task.previous) {
                  // If previous task is another day, will be null
                  const previousTask = _.find(this.props.allTasks, t => t['@id'] === task.previous)
                  if (previousTask) {
                    Array.prototype.splice.apply(newTasks,
                      Array.prototype.concat([ result.destination.index, 0 ], previousTask))
                  }
                } else if (task && task.next) {
                  // If next task is another day, will be null
                  const nextTask = _.find(this.props.allTasks, t => t['@id'] === task.next)
                  if (nextTask) {
                    Array.prototype.splice.apply(newTasks,
                      Array.prototype.concat([ result.destination.index + 1, 0 ], nextTask))
                  }
                }

              }

            }

            this.props.modifyTaskList(username, newTasks)

          }}>
          <Split
            sizes={ [ 50, 50 ] }
            direction={ this.props.splitDirection }
            style={{ display: 'flex', flexDirection: this.props.splitDirection === 'vertical' ? 'column' : 'row', width: '100%' }}
            // We need to use a "key" prop,
            // to force a re-render when the direction has changed
            key={ this.props.splitDirection }>
            <UnassignedTasks />
            <TaskLists couriersList={ this.props.couriersList } />
          </Split>
        </DragDropContext>
        <SearchPanel />
        <ContextMenu />
        <ToastContainer />
      </div>
    )
  }
}

function mapStateToProps(state) {

  return {
    couriersList: state.couriersList,
    searchIsOn: state.searchIsOn,
    allTasks: selectAllTasks(state),
    taskLists: selectTaskLists(state),
    selectedTasks: state.selectedTasks,
    date: selectSelectedDate(state),
    splitDirection: state.rightPanelSplitDirection,
  }
}

function mapDispatchToProps (dispatch) {

  return {
    toggleSearch: () => dispatch(toggleSearch()),
    closeSearch: () => dispatch(closeSearch()),
    modifyTaskList: (username, tasks) => dispatch(modifyTaskList(username, tasks)),
    clearSelectedTasks: () => dispatch(clearSelectedTasks()),
    createTaskList: (date, username) => dispatch(createTaskList(date, username)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(DashboardApp)
