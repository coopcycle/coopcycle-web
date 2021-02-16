import React from 'react'
import { connect } from 'react-redux'
import _ from 'lodash'
import { withTranslation } from 'react-i18next'

import { openAddUserModal } from '../redux/actions'
import TaskList from './TaskList'

import { selectTaskLists } from '../redux/selectors'

class TaskLists extends React.Component {

  componentDidMount() {
    // Hide other collapsibles when a collapsible is going to be shown
    $('#accordion').on('show.bs.collapse', '.collapse', () => {
      $('#accordion').find('.collapse.in').collapse('hide')
    })
  }

  render() {

    const { taskLists, taskListsLoading } = this.props

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
        <div
          id="accordion"
          className="dashboard__panel__scroll"
          style={{ opacity: taskListsLoading ? 0.7 : 1, pointerEvents: taskListsLoading ? 'none' : 'initial' }}>
          {
            _.map(taskLists, (taskList, index) => {

              if (this.props.hiddenCouriers.includes(taskList.username)) {
                return null
              }

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
    taskLists: selectTaskLists(state),
    taskListsLoading: state.dispatch.taskListsLoading,
    hiddenCouriers: state.filters.hiddenCouriers,
  }
}

function mapDispatchToProps (dispatch) {

  return {
    openAddUserModal: () => dispatch(openAddUserModal()),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(TaskLists))
