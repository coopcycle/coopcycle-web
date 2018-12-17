import React from 'react'
import { connect } from 'react-redux'
import Modal from 'react-modal'
import _ from 'lodash'
import { translate } from 'react-i18next'

import { addTaskList, closeAddUserModal, openAddUserModal } from '../store/actions'
import TaskList from './TaskList'
import autoScroll from 'dom-autoscroller'

class TaskLists extends React.Component {

  constructor(props) {
    super(props)
    this.state = {
      selectedCourier: '',
      isDragging: false
    }

    this.addUser = this.addUser.bind(this)
    this.onCourierSelect = this.onCourierSelect.bind(this)
  }

  componentDidMount() {
    // Hide other collapsibles when a collapsible is going to be shown
    $('#accordion').on('show.bs.collapse', '.collapse', () => {
      $('#accordion').find('.collapse.in').collapse('hide')
    })

    const self = this

    autoScroll([ this.refs.scrollable ], {
      margin: 20,
      maxSpeed: 5,
      scrollWhenOutside: false,
      // Can't use an arrow function, because "this" would be wrong
      autoScroll: function() {
        // Only scroll when the pointer is down, and there is a child being dragged.
        return this.down && self.state.isDragging
      }
    })
  }

  componentDidUpdate(prevProps) {
    if (this.props.isDragging !== prevProps.isDragging) {
      this.setState({ isDragging: this.props.isDragging })
    }
  }

  addUser() {
    this.props.addTaskList(this.state.selectedCourier)
    this.props.closeAddUserModal()
  }

  onCourierSelect (e) {
    this.setState({'selectedCourier': e.target.value })
  }

  render() {

    const { addModalIsOpen, taskListsLoading, couriersList } = this.props
    let { taskLists } = this.props
    let { selectedCourier } = this.state

    taskLists = _.orderBy(taskLists, 'username')

    // filter out couriers that are already in planning
    const availableCouriers = _.filter(couriersList, (courier) => !_.find(taskLists, (tL) => tL.username === courier.username))


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
          <div className="modal-header">
            <button type="button" className="close" onClick={this.props.closeAddUserModal} aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 className="modal-title" id="user-modal-label">{this.props.t('ADMIN_DASHBOARD_ADDUSER_TO_PLANNING')}</h4>
          </div>
          <div className="modal-body">
            <form method="post" className="form-horizontal">
              <div className="form-group" data-action="dispatch">
                <label htmlFor="courier" className="col-sm-2 control-label">
                  { this.props.t('ADMIN_DASHBOARD_COURIER') }
                </label>
                <div className="col-sm-10">
                  <select name="courier" className="form-control" value={selectedCourier} onChange={(e) => this.onCourierSelect(e)}>
                    <option></option>
                    {
                      availableCouriers.map(function (item, index) {
                        return (<option value={ item.username } key={ index }>{item.username}</option>)
                      })
                    }
                  </select>
                </div>
              </div>
            </form>
          </div>
          <div className="modal-footer">
            <button type="button" className="btn btn-default" onClick={this.props.closeAddUserModal}>{this.props.t('ADMIN_DASHBOARD_CANCEL')}</button>
            <button type="submit" className="btn btn-primary" onClick={(e) => this.addUser(e)}>{ this.props.t('ADMIN_DASHBOARD_ADD') }</button>
          </div>
        </Modal>
        <div
          ref="scrollable"
          id="accordion"
          className="dashboard__panel__scroll"
          style={{ opacity: taskListsLoading ? 0.7 : 1, pointerEvents: taskListsLoading ? 'none' : 'initial' }}>
          {
            _.map(taskLists, (taskList, index) => {
              let collapsed = !(index === 0)
              return (
                <TaskList
                  key={ taskList['@id'] }
                  ref={ taskList['@id'] }
                  collapsed={ collapsed }
                  username={ taskList.username }
                  distance={ taskList.distance }
                  duration={ taskList.duration }
                  items={ taskList.items }
                  taskListDidMount={ this.props.taskListDidMount }
                />
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
    taskLists: state.taskLists,
    taskListsLoading: state.taskListsLoading,
    isDragging: state.isDragging,
  }
}

function mapDispatchToProps (dispatch) {
  return {
    addTaskList: (date, username) => dispatch(addTaskList(date, username)),
    openAddUserModal: () => { dispatch(openAddUserModal()) },
    closeAddUserModal: () => { dispatch(closeAddUserModal()) }
  }
}

export default connect(mapStateToProps, mapDispatchToProps, null, { withRef: true })(translate()(TaskLists))
