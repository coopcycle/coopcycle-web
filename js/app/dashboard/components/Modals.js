import React from 'react';
import { connect } from 'react-redux';
import Modal from 'react-modal';

import { selectSelectedDate } from '../../coopcycle-frontend-js/logistics/redux';

import {
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
  closeTaskRescheduleModal,
} from '../redux/actions';
import TaskModalContent from './TaskModalContent';
import FiltersModalContent from './FiltersModalContent';
import SettingsModalContent from './SettingsModalContent';
import ImportModalContent from './ImportModalContent';
import AddUserModalContent from './AddUserModalContent';
import RecurrenceRuleModalContent from './RecurrenceRuleModalContent';
import ExportModalContent from './ExportModalContent';
import CreateGroupModalContent from './CreateGroupModalContent';
import AddTaskToGroupModalContent from './AddTaskToGroupModalContent';
import CreateDeliveryModalContent from './CreateDeliveryModalContent';
import CreateTourModalContent from './CreateTourModalContent';
import TaskRescheduleModalContent from './TaskRescheduleModalContent';
import TaskReportIncidentModalContent from './TaskReportIncidentModalContent';
import { usePreloadedState } from '../hooks/usePreloadedState';

function Modals(props) {
  usePreloadedState();

  const customStyle = { overlay: { zIndex: 2 } }; // higher than search results

  return (
    <React.Fragment>
      <Modal
        appElement={document.getElementById('dashboard')}
        style={customStyle}
        isOpen={props.taskModalIsOpen}
        onRequestClose={() => {
          props.closeNewTaskModal();
        }}
        className="ReactModal__Content--task-form"
        shouldCloseOnOverlayClick={true}>
        <TaskModalContent onCloseClick={props.closeNewTaskModal} />
      </Modal>
      <Modal
        appElement={document.getElementById('dashboard')}
        style={customStyle}
        isOpen={props.filtersModalIsOpen}
        onRequestClose={() => props.closeFiltersModal()}
        className="ReactModal__Content--filters"
        shouldCloseOnOverlayClick={true}>
        <FiltersModalContent />
      </Modal>
      <Modal
        appElement={document.getElementById('dashboard')}
        style={customStyle}
        isOpen={props.settingsModalIsOpen}
        onRequestClose={() => props.closeSettings()}
        className="ReactModal__Content--settings"
        shouldCloseOnOverlayClick={true}>
        <SettingsModalContent />
      </Modal>
      <Modal
        appElement={document.getElementById('dashboard')}
        style={customStyle}
        isOpen={props.importModalIsOpen}
        onRequestClose={() => props.closeImportModal()}
        className="ReactModal__Content--import"
        shouldCloseOnOverlayClick={true}>
        <ImportModalContent />
      </Modal>
      <Modal
        appElement={document.getElementById('dashboard')}
        style={customStyle}
        isOpen={props.addModalIsOpen}
        onRequestClose={() => props.closeAddUserModal()}
        className="ReactModal__Content--select-courier"
        shouldCloseOnOverlayClick={true}>
        <AddUserModalContent
          onClickClose={props.closeAddUserModal}
          onClickSubmit={selectedCouriers => {
            selectedCouriers.forEach(courier => {
              props.createTaskList(props.date, courier.username);
            });
            props.closeAddUserModal();
          }}
        />
      </Modal>
      <Modal
        appElement={document.getElementById('dashboard')}
        style={customStyle}
        isOpen={props.recurrenceRuleModalIsOpen}
        onRequestClose={() => props.closeRecurrenceRuleModal()}
        className="ReactModal__Content--recurrence"
        shouldCloseOnOverlayClick={true}>
        <RecurrenceRuleModalContent />
      </Modal>
      <Modal
        appElement={document.getElementById('dashboard')}
        style={customStyle}
        isOpen={props.exportModalIsOpen}
        onRequestClose={props.closeExportModal}
        className="ReactModal__Content--select-courier"
        shouldCloseOnOverlayClick={true}>
        <ExportModalContent
          onClickClose={props.closeExportModal}
          onClickSubmit={(start, end) => props.exportTasks(start, end)}
        />
      </Modal>
      <Modal
        appElement={document.getElementById('dashboard')}
        style={customStyle}
        isOpen={props.createGroupModalIsOpen}
        onRequestClose={props.closeCreateGroupModal}
        className="ReactModal__Content--select-courier"
        shouldCloseOnOverlayClick={true}>
        <CreateGroupModalContent onClickClose={props.closeCreateGroupModal} />
      </Modal>
      <Modal
        appElement={document.getElementById('dashboard')}
        style={customStyle}
        isOpen={props.addTaskToGroupModalIsOpen}
        onRequestClose={props.closeAddTaskToGroupModal}
        className="ReactModal__Content--select-courier"
        shouldCloseOnOverlayClick={true}>
        <AddTaskToGroupModalContent
          onClickClose={props.closeAddTaskToGroupModal}
        />
      </Modal>
      <Modal
        appElement={document.getElementById('dashboard')}
        style={customStyle}
        isOpen={props.isCreateDeliveryModalVisible}
        onRequestClose={props.closeCreateDeliveryModal}
        className="ReactModal__Content--select-courier"
        shouldCloseOnOverlayClick={true}>
        <CreateDeliveryModalContent />
      </Modal>
      <Modal
        appElement={document.getElementById('dashboard')}
        style={customStyle}
        isOpen={props.isCreateTourModalVisible}
        onRequestClose={props.closeCreateTourModal}
        className="ReactModal__Content--select-courier"
        shouldCloseOnOverlayClick={true}>
        <CreateTourModalContent />
      </Modal>
      <Modal
        appElement={document.getElementById('dashboard')}
        style={customStyle}
        isOpen={props.isTaskRescheduleModalVisible}
        onRequestClose={props.closeTaskRescheduleModal}
        className="ReactModal__Content--task-reschedule"
        shouldCloseOnOverlayClick={true}>
        <TaskRescheduleModalContent />
      </Modal>
      <Modal
        appElement={document.getElementById('dashboard')}
        style={customStyle}
        isOpen={props.reportIncidentModalIsOpen}
        onRequestClose={props.closeReportIncidentModal}
        className="ReactModal__Content--task-report-incident"
        shouldCloseOnOverlayClick={true}>
        <TaskReportIncidentModalContent />
      </Modal>
    </React.Fragment>
  );
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
  };
}

function mapDispatchToProps(dispatch) {
  return {
    closeNewTaskModal: () => dispatch(closeNewTaskModal()),
    closeFiltersModal: () => dispatch(closeFiltersModal()),
    openSettings: () => dispatch(openSettings()),
    closeSettings: () => dispatch(closeSettings()),
    closeImportModal: () => dispatch(closeImportModal()),
    closeAddUserModal: () => dispatch(closeAddUserModal()),
    createTaskList: (date, username) =>
      dispatch(createTaskList(date, username)),
    closeRecurrenceRuleModal: () => dispatch(closeRecurrenceRuleModal()),
    closeExportModal: () => dispatch(closeExportModal()),
    closeCreateGroupModal: () => dispatch(closeCreateGroupModal()),
    exportTasks: (start, end) => dispatch(exportTasks(start, end)),
    closeAddTaskToGroupModal: () => dispatch(closeAddTaskToGroupModal()),
    closeCreateDeliveryModal: () => dispatch(closeCreateDeliveryModal()),
    closeCreateTourModal: () => dispatch(closeCreateTourModal()),
    closeTaskRescheduleModal: () => dispatch(closeTaskRescheduleModal()),
    closeReportIncidentModal: () => dispatch(closeReportIncidentModal()),
  };
}

export default connect(mapStateToProps, mapDispatchToProps)(Modals);
