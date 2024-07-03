import React from 'react'
import { connect } from 'react-redux'
import Modal from 'react-modal'

import { selectSelectedDate } from '../../coopcycle-frontend-js/logistics/redux'

import {
  setCurrentTask,
  closeNewTaskModal,
  closeFiltersModal,
  openSettings,
  closeSettings,
  closeImportModal,
  closeAddUserModal,
  createTaskList,
  closeRecurrenceRuleModal,
  closeExportModal,
  closeCreateGroupModal,
  exportTasks,
  closeAddTaskToGroupModal,
  closeCreateDeliveryModal,
  closeCreateTourModal,
  closeReportIncidentModal,
  closeTaskRescheduleModal
} from '../redux/actions'
import TaskModalContent from './TaskModalContent'
import FiltersModalContent from './FiltersModalContent'
import SettingsModalContent from './SettingsModalContent'
import ImportModalContent from './ImportModalContent'
import AddUserModalContent from './AddUserModalContent'
import RecurrenceRuleModalContent from './RecurrenceRuleModalContent'
import ExportModalContent from './ExportModalContent'
import CreateGroupModalContent from './CreateGroupModalContent'
import AddTaskToGroupModalContent from './AddTaskToGroupModalContent'
import CreateDeliveryModalContent from './CreateDeliveryModalContent'
import CreateTourModalContent from './CreateTourModalContent'
import TaskRescheduleModalContent from "./TaskRescheduleModalContent";
import TaskReportIncidentModalContent from './TaskReportIncidentModalContent';

class Modals extends React.Component {

  render () {
    const customStyle = {overlay: {zIndex: 2}} // higher than search results

    return (
      <React.Fragment>
        <Modal
          appElement={ document.getElementById('dashboard') }
          style={customStyle}
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
          style={customStyle}
          isOpen={ this.props.filtersModalIsOpen }
          onRequestClose={ () => this.props.closeFiltersModal() }
          className="ReactModal__Content--filters"
          shouldCloseOnOverlayClick={ true }>
          <FiltersModalContent />
        </Modal>
        <Modal
          appElement={ document.getElementById('dashboard') }
          style={customStyle}
          isOpen={ this.props.settingsModalIsOpen }
          onRequestClose={ () => this.props.closeSettings() }
          className="ReactModal__Content--settings"
          shouldCloseOnOverlayClick={ true }>
          <SettingsModalContent />
        </Modal>
        <Modal
          appElement={ document.getElementById('dashboard') }
          style={customStyle}
          isOpen={ this.props.importModalIsOpen }
          onRequestClose={ () => this.props.closeImportModal() }
          className="ReactModal__Content--import"
          shouldCloseOnOverlayClick={ true }>
          <ImportModalContent />
        </Modal>
        <Modal
          appElement={ document.getElementById('dashboard') }
          style={customStyle}
          isOpen={ this.props.addModalIsOpen }
          onRequestClose={ () => this.props.closeAddUserModal() }
          className="ReactModal__Content--select-courier"
          shouldCloseOnOverlayClick={ true }>
          <AddUserModalContent
            onClickClose={ this.props.closeAddUserModal }
            onClickSubmit={ (selectedCouriers) => {
              selectedCouriers.forEach((courier) => {
                this.props.createTaskList(this.props.date, courier.username)
              })
              this.props.closeAddUserModal()
            }} />
        </Modal>
        <Modal
          appElement={ document.getElementById('dashboard') }
          style={customStyle}
          isOpen={ this.props.recurrenceRuleModalIsOpen }
          onRequestClose={ () => this.props.closeRecurrenceRuleModal() }
          className="ReactModal__Content--recurrence"
          shouldCloseOnOverlayClick={ true }>
          <RecurrenceRuleModalContent />
        </Modal>
        <Modal
          appElement={ document.getElementById('dashboard') }
          style={customStyle}
          isOpen={ this.props.exportModalIsOpen }
          onRequestClose={ this.props.closeExportModal }
          className="ReactModal__Content--select-courier"
          shouldCloseOnOverlayClick={ true }>
          <ExportModalContent
            onClickClose={ this.props.closeExportModal }
            onClickSubmit={ (start, end) => this.props.exportTasks(start, end) } />
        </Modal>
        <Modal
          appElement={ document.getElementById('dashboard') }
          style={customStyle}
          isOpen={ this.props.createGroupModalIsOpen }
          onRequestClose={ this.props.closeCreateGroupModal }
          className="ReactModal__Content--select-courier"
          shouldCloseOnOverlayClick={ true }>
          <CreateGroupModalContent
            onClickClose={ this.props.closeCreateGroupModal } />
        </Modal>
        <Modal
          appElement={ document.getElementById('dashboard') }
          style={customStyle}
          isOpen={ this.props.addTaskToGroupModalIsOpen }
          onRequestClose={ this.props.closeAddTaskToGroupModal }
          className="ReactModal__Content--select-courier"
          shouldCloseOnOverlayClick={ true }>
          <AddTaskToGroupModalContent
            onClickClose={ this.props.closeAddTaskToGroupModal } />
        </Modal>
        <Modal
          appElement={ document.getElementById('dashboard') }
          style={customStyle}
          isOpen={ this.props.isCreateDeliveryModalVisible }
          onRequestClose={ this.props.closeCreateDeliveryModal }
          className="ReactModal__Content--select-courier"
          shouldCloseOnOverlayClick={ true }>
          <CreateDeliveryModalContent />
        </Modal>
        <Modal
          appElement={ document.getElementById('dashboard') }
          style={customStyle}
          isOpen={ this.props.isCreateTourModalVisible }
          onRequestClose={ this.props.closeCreateTourModal }
          className="ReactModal__Content--select-courier"
          shouldCloseOnOverlayClick={ true }>
          <CreateTourModalContent />
        </Modal>
        <Modal
          appElement={ document.getElementById('dashboard') }
          style={customStyle}
          isOpen={ this.props.isTaskRescheduleModalVisible }
          onRequestClose={ this.props.closeTaskRescheduleModal }
          className="ReactModal__Content--task-reschedule"
          shouldCloseOnOverlayClick={ true }>
          <TaskRescheduleModalContent />
        </Modal>
        <Modal
          appElement={ document.getElementById('dashboard') }
          style={customStyle}
          isOpen={ this.props.reportIncidentModalIsOpen }
          onRequestClose={ this.props.closeReportIncidentModal }
          className="ReactModal__Content--task-report-incident"
          shouldCloseOnOverlayClick={ true }>
          <TaskReportIncidentModalContent />
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
    exportModalIsOpen: state.exportModalIsOpen,
    createGroupModalIsOpen: state.createGroupModalIsOpen,
    addTaskToGroupModalIsOpen: state.addTaskToGroupModalIsOpen,
    isCreateDeliveryModalVisible: state.isCreateDeliveryModalVisible,
    isCreateTourModalVisible: state.isCreateTourModalVisible,
    isTaskRescheduleModalVisible: state.isTaskRescheduleModalVisible,
    reportIncidentModalIsOpen: state.reportIncidentModalIsOpen,
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
    closeExportModal: () => dispatch(closeExportModal()),
    closeCreateGroupModal: () => dispatch(closeCreateGroupModal()),
    exportTasks: (start, end) => dispatch(exportTasks(start, end)),
    closeAddTaskToGroupModal: () => dispatch(closeAddTaskToGroupModal()),
    closeCreateDeliveryModal: () => dispatch(closeCreateDeliveryModal()),
    closeCreateTourModal: () => dispatch(closeCreateTourModal()),
    closeTaskRescheduleModal: () => dispatch(closeTaskRescheduleModal()),
    closeReportIncidentModal: () => dispatch(closeReportIncidentModal())
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(Modals)
