import React from 'react'
import { connect } from 'react-redux'
import _ from 'lodash'
import { withTranslation } from 'react-i18next'
import { ContextMenu, MenuItem, connectMenu } from 'react-contextmenu'
import moment from 'moment'

import { removeTasks, cancelTasks, moveToTop, moveToBottom, moveTasksToNextDay, moveTasksToNextWorkingDay } from '../redux/actions'
import { selectNextWorkingDay } from '../redux/selectors'

const UNASSIGN_SINGLE = 'UNASSIGN_SINGLE'
const UNASSIGN_MULTI = 'UNASSIGN_MULTI'
const CANCEL_MULTI = 'CANCEL_MULTI'
const MOVE_TO_TOP = 'MOVE_TO_TOP'
const MOVE_TO_BOTTOM = 'MOVE_TO_BOTTOM'
const MOVE_TO_NEXT_DAY_MULTI = 'MOVE_TO_NEXT_DAY_MULTI'
const MOVE_TO_NEXT_WORKING_DAY_MULTI = 'MOVE_TO_NEXT_WORKING_DAY_MULTI'

import { selectUnassignedTasks } from '../../coopcycle-frontend-js/dispatch/redux'

function _unassign(tasksToUnassign, removeTasks) {
  const tasksByUsername = _.groupBy(tasksToUnassign, task => task.assignedTo)
  _.forEach(tasksByUsername, (tasks, username) => removeTasks(username, tasks))
}

/**
 * The variable "trigger" contains the task that was right-clicked
 */
const DynamicMenu = ({
  id, trigger,
  unassignedTasks, selectedTasks, nextWorkingDay,
  removeTasks, cancelTasks, moveToTop, moveToBottom, moveTasksToNextDay, moveTasksToNextWorkingDay,
  t }) => {

  const tasksToUnassign =
    _.filter(selectedTasks, selectedTask =>
      !_.find(unassignedTasks, unassignedTask => unassignedTask['@id'] === selectedTask['@id']))

  const containsOnlyUnassignedTasks = !_.find(selectedTasks, t => t.isAssigned)

  const actions = []

  if (trigger) {

    const isAssigned = !_.find(unassignedTasks, unassignedTask => unassignedTask['@id'] === trigger.task['@id'])
    if (isAssigned) {
      actions.push(UNASSIGN_SINGLE)
      if (selectedTasks.length === 1) {
        actions.push(MOVE_TO_TOP)
        actions.push(MOVE_TO_BOTTOM)
      }
    }

    if (selectedTasks.length > 0) {

      const isTriggerSelected = _.find(selectedTasks, selectedTask => selectedTask['@id'] === trigger.task['@id'])

      if (isTriggerSelected) {
        if (tasksToUnassign.length > 0) {
          actions.push(UNASSIGN_MULTI)
        }
        if (containsOnlyUnassignedTasks) {
          actions.push(CANCEL_MULTI)
          actions.push(MOVE_TO_NEXT_DAY_MULTI)
          actions.push(MOVE_TO_NEXT_WORKING_DAY_MULTI)
        }
      }
    }

  }

  return (
    <ContextMenu id={ id }>
      { actions.includes(UNASSIGN_SINGLE) && (
        <MenuItem onClick={ () => _unassign([ trigger.task ], removeTasks) }>
          { t('ADMIN_DASHBOARD_UNASSIGN_TASK', { id: trigger.task.id }) }
        </MenuItem>
      )}
      { actions.includes(MOVE_TO_TOP) && (
        <MenuItem onClick={ () => moveToTop(trigger.task) }>
          { t('ADMIN_DASHBOARD_MOVE_TO_TOP') }
        </MenuItem>
      )}
      { actions.includes(MOVE_TO_BOTTOM) && (
        <MenuItem onClick={ () => moveToBottom(trigger.task) }>
          { t('ADMIN_DASHBOARD_MOVE_TO_BOTTOM') }
        </MenuItem>
      )}
      { actions.includes(UNASSIGN_MULTI) && (
        <MenuItem divider />
      )}
      { actions.includes(UNASSIGN_MULTI) && (
        <MenuItem onClick={ () => _unassign(tasksToUnassign, removeTasks) }>
          { t('ADMIN_DASHBOARD_UNASSIGN_TASKS_MULTI', { count: tasksToUnassign.length }) }
        </MenuItem>
      )}
      { actions.includes(CANCEL_MULTI) && (
        <MenuItem onClick={ () => cancelTasks(selectedTasks) }>
          { t('ADMIN_DASHBOARD_CANCEL_TASKS_MULTI', { count: selectedTasks.length }) }
        </MenuItem>
      )}
      { actions.includes(MOVE_TO_NEXT_DAY_MULTI) && (
        <MenuItem onClick={ () => moveTasksToNextDay(selectedTasks) }>
          { t('ADMIN_DASHBOARD_MOVE_TO_NEXT_DAY_MULTI', { count: selectedTasks.length }) }
        </MenuItem>
      )}
      { actions.includes(MOVE_TO_NEXT_WORKING_DAY_MULTI) && (
        <MenuItem onClick={ () => moveTasksToNextWorkingDay(selectedTasks) }>
          { t('ADMIN_DASHBOARD_MOVE_TO_NEXT_WORKING_DAY_MULTI', { count: selectedTasks.length, nextWorkingDay: moment(nextWorkingDay).format('LL') }) }
        </MenuItem>
      )}
      { actions.length === 0 && (
        <MenuItem disabled>
          { t('ADMIN_DASHBOARD_NO_ACTION_AVAILABLE') }
        </MenuItem>
      )}
    </ContextMenu>
  )
}

const Menu = connectMenu('dashboard')(DynamicMenu)

function mapStateToProps(state) {

  return {
    unassignedTasks: selectUnassignedTasks(state),
    selectedTasks: state.selectedTasks,
    nextWorkingDay: selectNextWorkingDay(state),
  }
}

function mapDispatchToProps(dispatch) {
  return {
    removeTasks: (username, tasks) => dispatch(removeTasks(username, tasks)),
    cancelTasks: tasks => dispatch(cancelTasks(tasks)),
    moveToTop: task => dispatch(moveToTop(task)),
    moveToBottom: task => dispatch(moveToBottom(task)),
    moveTasksToNextDay: tasks => dispatch(moveTasksToNextDay(tasks)),
    moveTasksToNextWorkingDay: tasks => dispatch(moveTasksToNextWorkingDay(tasks)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(Menu))
