import React from 'react'
import { connect } from 'react-redux'
import Modal from 'react-modal'
import _ from 'lodash'
import { withTranslation } from 'react-i18next'

import { createTaskList, closeAddUserModal, openAddUserModal, openNewTaskModal, closeNewTaskModal, setCurrentTask } from '../redux/actions'
import TaskList from './TaskList'
import AddUserModalContent from './AddUserModalContent'

import { selectSelectedDate } from '../../coopcycle-frontend-js/dispatch/redux'
import { selectTaskLists } from '../redux/selectors'

class TaskLists extends React.Component {

  componentDidMount() {
    // Hide other collapsibles when a collapsible is going to be shown
    $('#accordion').on('show.bs.collapse', '.collapse', () => {
      $('#accordion').find('.collapse.in').collapse('hide')
    })
  }

  render() {

    const { addModalIsOpen, taskListsLoading } = this.props
    const { taskLists } = this.props

    return (
      <div className="dashboard__panel dashboard__panel--assignees">
        <h4>
          <span>{ this.props.t('DASHBOARD_ASSIGNED') }</span>
          { taskListsLoading ?
            (<span className="pull-right"><i className="fa fa-spinner"></i></span>) :
            (<a className="pull-right" onClick={this.props.openAddUserModal}>
              <i className="fa fa-plus"></i>&nbsp;<i className="fa fa-user"></i>
            </a>)
          }
        </h4>
        <Modal
          appElement={document.getElementById('dashboard')}
          isOpen={addModalIsOpen}
          className="ReactModal__Content--select-courier">
          <AddUserModalContent
            onClickClose={ this.props.closeAddUserModal }
            onClickSubmit={ username => {
              this.props.createTaskList(this.props.date, username)
              this.props.closeAddUserModal()
            }} />
        </Modal>
        <div
          id="accordion"
          className="dashboard__panel__scroll"
          style={{ opacity: taskListsLoading ? 0.7 : 1, pointerEvents: taskListsLoading ? 'none' : 'initial' }}>
          {
            _.map(taskLists, (taskList, index) => {
              let collapsed = !(index === 0)
              return (
                <TaskList
                  key={ taskList['@id'] }
                  collapsed={ collapsed }
                  username={ taskList.username }
                  distance={ taskList.distance }
                  duration={ taskList.duration }
                  items={ taskList.items }
                  uri={ taskList['@id'] } />
              )
            })
          }
        </div>
      </div>
    )
  }
}

function mapStateToProps (state) {

  return {
    addModalIsOpen: state.addModalIsOpen,
    taskLists: selectTaskLists(state),
    date: selectSelectedDate(state),
    taskListsLoading: state.dispatch.taskListsLoading,
  }
}

function mapDispatchToProps (dispatch) {

  return {
    createTaskList: (date, username) => dispatch(createTaskList(date, username)),
    openAddUserModal: () => { dispatch(openAddUserModal()) },
    closeAddUserModal: () => { dispatch(closeAddUserModal()) },
    openNewTaskModal: () => dispatch(openNewTaskModal()),
    closeNewTaskModal: () => dispatch(closeNewTaskModal()),
    setCurrentTask: (task) => dispatch(setCurrentTask(task)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(TaskLists))
