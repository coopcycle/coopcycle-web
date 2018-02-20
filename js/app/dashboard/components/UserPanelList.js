import React from 'react'
import { connect } from 'react-redux'
import { findDOMNode } from 'react-dom'
import Modal from 'react-modal'
import _ from 'lodash'
import { addUsernameToList, closeAddUserModal, openAddUserModal } from '../store/actions'
import UserPanel from './UserPanel'


class UserPanelList extends React.Component {

  constructor(props) {
    super(props)
    this.state = {
      tasks: props.tasks || [],
      uncollapsed: null,
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
    this.props.addUsername(this.state.selectedCourier)
    this.props.closeAddUserModal()
  }

  onCourierSelect (e) {
    this.setState({'selectedCourier': e.target.value })
  }

  render() {

    let { addModalIsOpen, assignedTasksByUser, userPanelLoading } = this.props
    let { uncollapsed, selectedCourier } = this.state

    if (!uncollapsed) {
      uncollapsed = _.first(_.keys(assignedTasksByUser))
    }

    return (
      <div className="dashboard__panel">
        <h4>
          <span>{ window.AppData.Dashboard.i18n['Assigned'] }</span>
          { userPanelLoading ?
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
                      this.props.couriersList.map(function (item, index) {
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
        <div className="dashboard__panel__scroll" style={{ opacity: userPanelLoading ? 0.7 : 1, pointerEvents: userPanelLoading ? 'none' : 'initial' }}>
          <div id="accordion">
          {
            _.map(assignedTasksByUser, (tasks, username) => {
              return (
                <UserPanel
                  key={ username }
                  username={ username }
                  collapsed={ uncollapsed !== username }
                  onLoad={ (element) => this.props.onLoad(element.querySelector('.panel .list-group')) } />
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
    assignedTasksByUser: state.assignedTasksByUser,
    userPanelLoading: state.userPanelLoading
  }
}

function mapDispatchToProps (dispatch) {
  return {
    addUsername: (username) => { dispatch(addUsernameToList(username)) },
    openAddUserModal: () => { dispatch(openAddUserModal()) },
    closeAddUserModal: () => { dispatch(closeAddUserModal()) }
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(UserPanelList)
