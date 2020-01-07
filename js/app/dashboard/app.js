import React from 'react'
import { connect } from 'react-redux'
import Modal from 'react-modal'

import {
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
        <UnassignedTasks />
        <TaskLists couriersList={ this.props.couriersList } />
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
    taskModalIsOpen: state.taskModalIsOpen,
    couriersList: state.couriersList,
    filtersModalIsOpen: state.filtersModalIsOpen,
    settingsModalIsOpen: state.settingsModalIsOpen,
    searchIsOn: state.searchIsOn
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
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(DashboardApp)
