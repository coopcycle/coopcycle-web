import React from 'react'
import { connect } from 'react-redux'
import _ from 'lodash'
import { withTranslation } from 'react-i18next'
import { Accordion } from 'react-accessible-accordion'

import { openAddUserModal } from '../redux/actions'
import TaskList from './TaskList'

import { selectHiddenCouriersSetting, selectTaskLists, selectTaskListsLoading } from '../redux/selectors'

class TaskLists extends React.Component {

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
        <Accordion
          allowZeroExpanded
          id="accordion"
          className="dashboard__panel__scroll"
          style={{ opacity: taskListsLoading ? 0.7 : 1, pointerEvents: taskListsLoading ? 'none' : 'initial' }}>
          {
            _.map(taskLists, (taskList) => {

              if (this.props.hiddenCouriers.includes(taskList.username)) {
                return null
              }

              return (
                <TaskList
                  key={ taskList['@id'] }
                  username={ taskList.username }
                  distance={ taskList.distance }
                  duration={ taskList.duration }
                  uri={ taskList['@id'] } />
              )
            })
          }
        </Accordion>
      </div>
    )
  }
}

function mapStateToProps (state) {

  return {
    taskLists: selectTaskLists(state),
    taskListsLoading: selectTaskListsLoading(state),
    hiddenCouriers: selectHiddenCouriersSetting(state),
  }
}

function mapDispatchToProps (dispatch) {

  return {
    openAddUserModal: () => dispatch(openAddUserModal()),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(TaskLists))
