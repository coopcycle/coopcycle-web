import React from 'react'
import { connect } from 'react-redux'
import Modal from 'react-modal'
import { DragDropContext } from 'react-beautiful-dnd'
import _ from 'lodash'

import { ToastContainer } from 'react-toastify'
import 'react-toastify/dist/ReactToastify.css'
import { selectAllTasks, selectTaskLists } from '../coopcycle-frontend-js/logistics/redux'

import {
  setCurrentTask,
  closeNewTaskModal,
  closeFiltersModal,
  toggleSearch,
  closeSearch,
  openSettings,
  closeSettings,
  closeImportModal,
  modifyTaskList,
  clearSelectedTasks } from './redux/actions'
import UnassignedTasks from './components/UnassignedTasks'
import TaskLists from './components/TaskLists'
import ContextMenu from './components/ContextMenu'
import TaskModalContent from './components/TaskModalContent'
import FiltersModalContent from './components/FiltersModalContent'
import SettingsModalContent from './components/SettingsModalContent'
import ImportModalContent from './components/ImportModalContent'
import SearchPanel from './components/SearchPanel'

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
          <UnassignedTasks />
          <TaskLists couriersList={ this.props.couriersList } />
        </DragDropContext>
        <SearchPanel />
        <ContextMenu />
        <Modal
          appElement={ document.getElementById('dashboard') }
          isOpen={ this.props.taskModalIsOpen }
          onRequestClose={ () => {
            this.props.setCurrentTask(null)
          }}
          className="ReactModal__Content--task-form"
          shouldCloseOnOverlayClick={ true }>
          <TaskModalContent />
        </Modal>
        <Modal
          appElement={ document.getElementById('dashboard') }
          isOpen={ this.props.filtersModalIsOpen }
          onRequestClose={ () => this.props.closeFiltersModal() }
          className="ReactModal__Content--filters"
          shouldCloseOnOverlayClick={ true }>
          <FiltersModalContent />
        </Modal>
        <Modal
          appElement={ document.getElementById('dashboard') }
          isOpen={ this.props.settingsModalIsOpen }
          onRequestClose={ () => this.props.closeSettings() }
          className="ReactModal__Content--settings"
          shouldCloseOnOverlayClick={ true }>
          <SettingsModalContent />
        </Modal>
        <Modal
          appElement={ document.getElementById('dashboard') }
          isOpen={ this.props.importModalIsOpen }
          onRequestClose={ () => this.props.closeImportModal() }
          className="ReactModal__Content--import"
          shouldCloseOnOverlayClick={ true }>
          <ImportModalContent />
        </Modal>
        <ToastContainer />
      </div>
    )
  }
}

function mapStateToProps(state) {

  return {
    taskModalIsOpen: state.taskModalIsOpen,
    couriersList: state.couriersList,
    filtersModalIsOpen: state.filtersModalIsOpen,
    settingsModalIsOpen: state.settingsModalIsOpen,
    searchIsOn: state.searchIsOn,
    importModalIsOpen: state.importModalIsOpen,
    allTasks: selectAllTasks(state),
    taskLists: selectTaskLists(state),
    selectedTasks: state.selectedTasks,
  }
}

function mapDispatchToProps (dispatch) {

  return {
    setCurrentTask: (task) => dispatch(setCurrentTask(task)),
    closeNewTaskModal: () => dispatch(closeNewTaskModal()),
    closeFiltersModal: () => dispatch(closeFiltersModal()),
    toggleSearch: () => dispatch(toggleSearch()),
    closeSearch: () => dispatch(closeSearch()),
    openSettings: () => dispatch(openSettings()),
    closeSettings: () => dispatch(closeSettings()),
    closeImportModal: () => dispatch(closeImportModal()),
    modifyTaskList: (username, tasks) => dispatch(modifyTaskList(username, tasks)),
    clearSelectedTasks: () => dispatch(clearSelectedTasks()),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(DashboardApp)
