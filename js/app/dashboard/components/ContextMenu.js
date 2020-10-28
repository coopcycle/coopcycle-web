import React from 'react'
import { connect } from 'react-redux'
import _ from 'lodash'
import { withTranslation } from 'react-i18next'
import { ContextMenu, MenuItem, connectMenu } from 'react-contextmenu'

import { removeTasks, cancelTasks, moveToTop, moveToBottom } from '../redux/actions'

const UNASSIGN_SINGLE = 'UNASSIGN_SINGLE'
const UNASSIGN_MULTI = 'UNASSIGN_MULTI'
const CANCEL_MULTI = 'CANCEL_MULTI'
const MOVE_TO_TOP = 'MOVE_TO_TOP'
const MOVE_TO_BOTTOM = 'MOVE_TO_BOTTOM'

import { selectUnassignedTasks } from '../../coopcycle-frontend-js/lastmile/redux'


function _unassign(tasksToUnassign, removeTasks) {
  const tasksByUsername = _.groupBy(tasksToUnassign, task => task.assignedTo)
  _.forEach(tasksByUsername, (tasks, username) => removeTasks(username, tasks))
}

/**
 * The variable "trigger" contains the task that was right-clicked
 */
const DynamicMenu = ({
  id, trigger,
  unassignedTasks, selectedTasks, tasksToUnassign, containsOnlyUnassignedTasks,
  removeTasks, cancelTasks, moveToTop, moveToBottom, t }) => {

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

  const tasksToUnassign =
      _.filter(state.selectedTasks, selectedTask =>
        !_.find(selectUnassignedTasks(state), unassignedTask => unassignedTask['@id'] === selectedTask['@id']))

  const containsOnlyUnassignedTasks = !_.find(state.selectedTasks, t => t.isAssigned)

  return {
    unassignedTasks: selectUnassignedTasks(state),
    selectedTasks: state.selectedTasks,
    tasksToUnassign,
    containsOnlyUnassignedTasks,
  }
}

function mapDispatchToProps(dispatch) {
  return {
    removeTasks: (username, tasks) => dispatch(removeTasks(username, tasks)),
    cancelTasks: tasks => dispatch(cancelTasks(tasks)),
    moveToTop: task => dispatch(moveToTop(task)),
    moveToBottom: task => dispatch(moveToBottom(task)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(Menu))
