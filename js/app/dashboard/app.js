/* eslint-disable react/no-find-dom-node */

import React from 'react'
import { findDOMNode } from 'react-dom'
import { connect } from 'react-redux'
import dragula from 'dragula'
import _ from 'lodash'
import Modal from 'react-modal'

import {
  assignTasks,
  drakeDrag,
  drakeDragEnd,
  setCurrentTask,
  closeNewTaskModal,
  closeFiltersModal,
  toggleSearch,
  closeSearch,
  openSettings,
  closeSettings } from './redux/actions'
import UnassignedTasks from './components/UnassignedTasks'
import TaskLists from './components/TaskLists'
import ContextMenu from './components/ContextMenu'
import TaskModalContent from './components/TaskModalContent'
import FiltersModalContent from './components/FiltersModalContent'
import SettingsModalContent from './components/SettingsModalContent'
import SearchPanel from './components/SearchPanel'

const drake = dragula({
  copy: true,
  copySortSource: false,
  revertOnSpill: true,
  accepts: (el, target, source) => target !== source
})

/**
 * Code to handle drag and drop from unassigned tasks to assigned
 */

function onTaskDrop(allTasks, assignTasks, element, target) {

  const username = $(target).data('username')
  const isTask = element.hasAttribute('data-task-id')

  let tasks = []

  if (isTask) { // This is a single task

    const task = _.find(allTasks, task => task['@id'] === element.getAttribute('data-task-id'))
    tasks = [ task ]

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
    .on('drag', function(el) {
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
    .on('cloned', function (clone) {
      clone.classList.remove('task__draggable--dragging')
    })
    .on('over', function (el, container) {
      if ($(container).hasClass('dropzone')) {
        $(container).addClass('dropzone--over')
      }
    })
    .on('out', function (el, container) {
      if ($(container).hasClass('dropzone')) {
        $(container).removeClass('dropzone--over')
      }
    })
}

function configureDragEnd(unassignedTasksContainer, drakeDragEnd) {
  drake
    .off('dragend')
    .on('dragend', function () {
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

  constructor(props) {
    super(props)

    this.unassignedTasksRef = React.createRef()
  }

  componentDidMount() {

    const unassignedTasksContainer = findDOMNode(this.unassignedTasksRef.current).querySelector('.list-group')
    drake.containers.push(unassignedTasksContainer)

    configureDrag(this.props.drakeDrag)
    configureDragEnd(unassignedTasksContainer, this.props.drakeDragEnd)
    configureDrop(this.props.allTasks, this.props.assignTasks)

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

  componentDidUpdate(prevProps) {
    if (this.props.allTasks !== prevProps.allTasks) {
      configureDrop(this.props.allTasks, this.props.assignTasks)
    }
  }

  render () {
    return (
      <div className="dashboard__aside-container">
        <UnassignedTasks
          ref={ this.unassignedTasksRef } />
        <TaskLists
          couriersList={ this.props.couriersList }
          taskListDidMount={ taskListComponent =>
            drake.containers.push(findDOMNode(taskListComponent).querySelector('.panel .list-group'))
          }
        />
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
      </div>
    )
  }
}

function mapStateToProps(state) {
  return {
    allTasks: state.allTasks,
    taskModalIsOpen: state.taskModalIsOpen,
    couriersList: state.couriersList,
    filtersModalIsOpen: state.filtersModalIsOpen,
    settingsModalIsOpen: state.settingsModalIsOpen,
    searchIsOn: state.searchIsOn
  }
}

function mapDispatchToProps (dispatch) {
  return {
    assignTasks: (username, tasks) => dispatch(assignTasks(username, tasks)),
    drakeDrag: () => dispatch(drakeDrag()),
    drakeDragEnd: () => dispatch(drakeDragEnd()),
    setCurrentTask: (task) => dispatch(setCurrentTask(task)),
    closeNewTaskModal: () => dispatch(closeNewTaskModal()),
    closeFiltersModal: () => dispatch(closeFiltersModal()),
    toggleSearch: () => dispatch(toggleSearch()),
    closeSearch: () => dispatch(closeSearch()),
    openSettings: () => dispatch(openSettings()),
    closeSettings: () => dispatch(closeSettings()),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(DashboardApp)
