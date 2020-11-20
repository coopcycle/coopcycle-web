import React from 'react'
import { connect } from 'react-redux'
import Modal from 'react-modal'
import _ from 'lodash'
import { withTranslation } from 'react-i18next'

import { createTaskList, closeAddUserModal, openAddUserModal, openNewTaskModal, closeNewTaskModal, setCurrentTask } from '../redux/actions'
import CourierSelect from './CourierSelect'
import TaskList from './TaskList'

import { selectTaskLists, selectSelectedDate } from '../../coopcycle-frontend-js/lastmile/redux'

class TaskLists extends React.Component {

  constructor(props) {
    super(props)
    this.state = {
      selectedCourier: '',
    }

    this.addUser = this.addUser.bind(this)
  }

  componentDidMount() {
    // Hide other collapsibles when a collapsible is going to be shown
    $('#accordion').on('show.bs.collapse', '.collapse', () => {
      $('#accordion').find('.collapse.in').collapse('hide')
    })
  }

  addUser() {
    this.props.createTaskList(this.props.date, this.state.selectedCourier)
    this.props.closeAddUserModal()
  }

  render() {

    const { addModalIsOpen, taskListsLoading } = this.props
    let { taskLists } = this.props

    taskLists = _.orderBy(taskLists, 'username')

    const classNames = ['dashboard__panel', 'dashboard__panel--assignees']
    if (this.props.hidden) {
      classNames.push('hidden')
    }

    return (
      <div className={ classNames.join(' ') }>
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
          <div className="modal-header">
            <button type="button" className="close" onClick={this.props.closeAddUserModal} aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 className="modal-title" id="user-modal-label">{this.props.t('ADMIN_DASHBOARD_ADDUSER_TO_PLANNING')}</h4>
          </div>
          <div className="modal-body">
            <form method="post" >
              <div className="form-group" data-action="dispatch">
                <label htmlFor="courier" className="control-label">
                  { this.props.t('ADMIN_DASHBOARD_COURIER') }
                </label>
                <CourierSelect
                  onChange={ courier => this.setState({ selectedCourier: courier.username }) }
                  exclude />
              </div>
            </form>
          </div>
          <div className="modal-footer">
            <button type="button" className="btn btn-default" onClick={ this.props.closeAddUserModal }>{this.props.t('ADMIN_DASHBOARD_CANCEL')}</button>
            <button type="submit" className="btn btn-primary" onClick={ (e) => this.addUser(e) }>{ this.props.t('ADMIN_DASHBOARD_ADD') }</button>
          </div>
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
                  items={ taskList.items } />
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
    taskListsLoading: state.lastmile.ui.taskListsLoading,
    taskModalIsOpen: state.taskModalIsOpen,
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
