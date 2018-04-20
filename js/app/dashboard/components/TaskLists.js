import React from 'react'
import { connect } from 'react-redux'
import Modal from 'react-modal'
import _ from 'lodash'
import { addTaskList, closeAddUserModal, openAddUserModal } from '../store/actions'
import TaskList from './TaskList'

class TaskLists extends React.Component {

  constructor(props) {
    super(props)
    this.state = {
      selectedCourier: ''
    }

    this.addUser = this.addUser.bind(this)
    this.onCourierSelect = this.onCourierSelect.bind(this)
  }

  componentDidMount() {
    // Hide other collapsibles when a collapsible is going to be shown
    $('#accordion').on('show.bs.collapse', '.collapse', () => {
      $('#accordion').find('.collapse.in').collapse('hide')
    });
  }

  addUser() {
    this.props.addTaskList(this.state.selectedCourier)
    this.props.closeAddUserModal()
  }

  onCourierSelect (e) {
    this.setState({'selectedCourier': e.target.value })
  }

  render() {

    const { addModalIsOpen, taskLists, taskListsLoading, couriersList } = this.props
    let { selectedCourier } = this.state

    // filter out couriers that are already in planning
    const availableCouriers = _.filter(couriersList, (courier) => !_.find(taskLists, (tL) => tL.username === courier.username))

    return (
      <div className="dashboard__panel dashboard__panel--assignees">
        <h4>
          <span>{ window.AppData.Dashboard.i18n['Assigned'] }</span>
          { taskListsLoading ?
            (<span className="pull-right"><i className="fa fa-spinner"></i></span>) :
            (<a className="pull-right" onClick={this.props.openAddUserModal}>
              <i className="fa fa-plus"></i> <i className="fa fa-user"></i>
            </a>)
          }
        </h4>
        <Modal
          appElement={document.getElementById('dashboard')}
          isOpen={addModalIsOpen}
          style={{
            content: {
              position: 'absolute',
              top: '60px',
              bottom: 'auto',
              margin: 'auto',
              width: '440px',
              border: '1px solid #ccc',
              background: '#fff',
              overflow: 'auto',
              borderRadius: '4px',
              outline: 'none',
              padding: '20px'
            }
          }}
        >
          <div className="modal-header">
            <button type="button" className="close" onClick={this.props.closeAddUserModal} aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 className="modal-title" id="user-modal-label">{window.AppData.Dashboard.i18n['Add a user to the planning']}</h4>
          </div>
          <div className="modal-body">
            <form method="post" className="form-horizontal">
              <div className="form-group" data-action="dispatch">
                <label htmlFor="courier" className="col-sm-2 control-label">
                  {window.AppData.Dashboard.i18n['Courier']}
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
            <button type="button" className="btn btn-default" onClick={this.props.closeAddUserModal}>{window.AppData.Dashboard.i18n['Cancel']}</button>
            <button type="submit" className="btn btn-primary" onClick={(e) => this.addUser(e)}>{window.AppData.Dashboard.i18n['Add']}</button>
          </div>
        </Modal>
        <div className="dashboard__panel__scroll" style={{ opacity: taskListsLoading ? 0.7 : 1, pointerEvents: taskListsLoading ? 'none' : 'initial' }}>
          <div id="accordion">
          {
            _.map(taskLists, taskList => {
              return (
                <TaskList
                  key={ taskList['@id'] }
                  ref={ taskList['@id'] }
                  username={ taskList.username }
                  distance={ taskList.distance }
                  duration={ taskList.duration }
                  items={ taskList.items }
                  taskListDidMount={ this.props.taskListDidMount } />
              )
            })
          }
          </div>
        </div>
      </div>
    )
  }
}

function mapStateToProps (state) {
  return {
    addModalIsOpen: state.addModalIsOpen,
    taskLists: state.taskLists,
    taskListsLoading: state.taskListsLoading
  }
}

function mapDispatchToProps (dispatch) {
  return {
    addTaskList: (date, username) => dispatch(addTaskList(date, username)),
    openAddUserModal: () => { dispatch(openAddUserModal()) },
    closeAddUserModal: () => { dispatch(closeAddUserModal()) }
  }
}

export default connect(mapStateToProps, mapDispatchToProps, null, { withRef: true })(TaskLists)
