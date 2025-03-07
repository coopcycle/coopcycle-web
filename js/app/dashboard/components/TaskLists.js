import React from 'react'
import { connect } from 'react-redux'
import _ from 'lodash'
import { withTranslation } from 'react-i18next'

import { openAddUserModal, setMapFilterValue } from '../redux/actions'
import TaskList from './TaskList'

import { selectHiddenCouriersSetting, selectMapFiltersSetting, selectTaskLists, selectTaskListsLoading } from '../redux/selectors'
import { Switch, Tooltip } from 'antd'

class TaskLists extends React.Component {

  render() {

    const { taskLists, taskListsLoading, setMapFilterValue, mapFiltersSettings } = this.props

    return (
      <div className="dashboard__panel dashboard__panel--assignees">
        <h4 className="dashboard__panel__header d-flex justify-content-between">
          <span>{ this.props.t('DASHBOARD_ASSIGNED') }</span>
          <span className="pull-right">
            <Tooltip
              title={this.props.t("ADMIN_DASHBOARD_HIDE_SHOW_ON_MAP")}
              placement="left"
              className="mr-2"
            >
              <Switch
                unCheckedChildren={"0"}
                checkedChildren={"I"}
                defaultChecked={mapFiltersSettings.showAssigned}
                checked={mapFiltersSettings.showAssigned}
                onChange={checked => setMapFilterValue(checked)}
              />
            </Tooltip>
            { taskListsLoading ?
              (<span className="loader"></span>) :
              (<a onClick={this.props.openAddUserModal}>
                <i className="fa fa-plus" data-cypress-add-to-planning></i>&nbsp;<i className="fa fa-user"></i>
              </a>)
            }
          </span>
        </h4>
        <div
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
                  taskListsLoading={ taskListsLoading }
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
    taskLists: selectTaskLists(state),
    taskListsLoading: selectTaskListsLoading(state),
    hiddenCouriers: selectHiddenCouriersSetting(state),
    mapFiltersSettings: selectMapFiltersSetting(state)
  }
}

function mapDispatchToProps (dispatch) {

  return {
    openAddUserModal: () => dispatch(openAddUserModal()),
    setMapFilterValue: (checked) => dispatch(setMapFilterValue({key: "showAssigned", value: checked}))
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(TaskLists))
