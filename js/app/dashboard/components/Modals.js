import React from 'react'
import { connect } from 'react-redux'
import Modal from 'react-modal'

import { selectSelectedDate } from '../../coopcycle-frontend-js/dispatch/redux'

import {
  setCurrentTask,
  closeNewTaskModal,
  closeFiltersModal,
  openSettings,
  closeSettings,
  closeImportModal,
  closeAddUserModal,
  createTaskList,
  closeRecurrenceRuleModal } from '../redux/actions'
import TaskModalContent from './TaskModalContent'
import FiltersModalContent from './FiltersModalContent'
import SettingsModalContent from './SettingsModalContent'
import ImportModalContent from './ImportModalContent'
import AddUserModalContent from './AddUserModalContent'
import RecurrenceRuleModalContent from './RecurrenceRuleModalContent'

class Modals extends React.Component {

  render () {
    return (
      <React.Fragment>
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
        <Modal
          appElement={ document.getElementById('dashboard') }
          isOpen={ this.props.addModalIsOpen }
          onRequestClose={ () => this.props.closeAddUserModal() }
          className="ReactModal__Content--select-courier"
          shouldCloseOnOverlayClick={ true }>
          <AddUserModalContent
            onClickClose={ this.props.closeAddUserModal }
            onClickSubmit={ username => {
              this.props.createTaskList(this.props.date, username)
              this.props.closeAddUserModal()
            }} />
        </Modal>
        <Modal
          appElement={ document.getElementById('dashboard') }
          isOpen={ this.props.recurrenceRuleModalIsOpen }
          onRequestClose={ () => this.props.closeRecurrenceRuleModal() }
          className="ReactModal__Content--recurrence"
          shouldCloseOnOverlayClick={ true }>
          <RecurrenceRuleModalContent />
        </Modal>
      </React.Fragment>
    )
  }
}

function mapStateToProps(state) {

  return {
    taskModalIsOpen: state.taskModalIsOpen,
    filtersModalIsOpen: state.filtersModalIsOpen,
    settingsModalIsOpen: state.settingsModalIsOpen,
    importModalIsOpen: state.importModalIsOpen,
    addModalIsOpen: state.addModalIsOpen,
    date: selectSelectedDate(state),
    recurrenceRuleModalIsOpen: state.recurrenceRuleModalIsOpen,
  }
}

function mapDispatchToProps (dispatch) {

  return {
    setCurrentTask: (task) => dispatch(setCurrentTask(task)),
    closeNewTaskModal: () => dispatch(closeNewTaskModal()),
    closeFiltersModal: () => dispatch(closeFiltersModal()),
    openSettings: () => dispatch(openSettings()),
    closeSettings: () => dispatch(closeSettings()),
    closeImportModal: () => dispatch(closeImportModal()),
    closeAddUserModal: () => dispatch(closeAddUserModal()),
    createTaskList: (date, username) => dispatch(createTaskList(date, username)),
    closeRecurrenceRuleModal: () => dispatch(closeRecurrenceRuleModal()),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(Modals)
