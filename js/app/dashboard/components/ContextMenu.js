import React from 'react'
import { connect } from 'react-redux'
import _ from 'lodash'
import { useTranslation } from 'react-i18next'
import { Menu, Item } from 'react-contexify'

import moment from 'moment'

import { unassignTasks, cancelTasks, moveToTop, moveToBottom, moveTasksToNextDay, moveTasksToNextWorkingDay, openCreateGroupModal, openAddTaskToGroupModal, removeTaskFromGroup } from '../redux/actions'
import { selectNextWorkingDay, selectSelectedTasks } from '../redux/selectors'

const UNASSIGN_SINGLE = 'UNASSIGN_SINGLE'
const UNASSIGN_MULTI = 'UNASSIGN_MULTI'
const CANCEL_MULTI = 'CANCEL_MULTI'
const MOVE_TO_TOP = 'MOVE_TO_TOP'
const MOVE_TO_BOTTOM = 'MOVE_TO_BOTTOM'
const MOVE_TO_NEXT_DAY_MULTI = 'MOVE_TO_NEXT_DAY_MULTI'
const MOVE_TO_NEXT_WORKING_DAY_MULTI = 'MOVE_TO_NEXT_WORKING_DAY_MULTI'
const CREATE_GROUP = 'CREATE_GROUP'
const ADD_TO_GROUP = 'ADD_TO_GROUP'
const REMOVE_FROM_GROUP = 'REMOVE_FROM_GROUP'

import { selectUnassignedTasks } from '../../coopcycle-frontend-js/logistics/redux'

import 'react-contexify/dist/ReactContexify.css'

function _unassign(tasksToUnassign, unassignTasks) {
  const tasksByUsername = _.groupBy(tasksToUnassign, task => task.assignedTo)
  _.forEach(tasksByUsername, (tasks, username) => unassignTasks(username, tasks))
}

const DynamicMenu = ({
  unassignedTasks, selectedTasks, nextWorkingDay,
  unassignTasks, cancelTasks, moveToTop, moveToBottom, moveTasksToNextDay, moveTasksToNextWorkingDay,
  openCreateGroupModal, openAddTaskToGroupModal, removeTaskFromGroup
}) => {

  const { t } = useTranslation()

  const tasksToUnassign =
    _.filter(selectedTasks, selectedTask =>
      !_.find(unassignedTasks, unassignedTask => unassignedTask['@id'] === selectedTask['@id']))

  const containsOnlyUnassignedTasks = !_.find(selectedTasks, t => t.isAssigned)

  const containsOnlyGroupedTasks = selectedTasks.every(task => Object.prototype.hasOwnProperty.call(task, 'group') && task.group)

  const actions = []

  let selectedTask

  if (selectedTasks.length > 0) {

    const isMultiple = selectedTasks.length > 1

    if (isMultiple) {

      if (tasksToUnassign.length > 0) {
        actions.push(UNASSIGN_MULTI)
      }

      if (containsOnlyUnassignedTasks) {
        actions.push(CANCEL_MULTI)
        actions.push(CREATE_GROUP)
      }

      if (containsOnlyGrouppedTasks) {
        actions.push(REMOVE_FROM_GROUP)
      }

    } else {

      selectedTask = selectedTasks[0]

      const isAssigned = !_.find(unassignedTasks, unassignedTask => unassignedTask['@id'] === selectedTask['@id'])

      if (isAssigned) {
        actions.push(UNASSIGN_SINGLE)
        actions.push(MOVE_TO_TOP)
        actions.push(MOVE_TO_BOTTOM)
      } else {
        actions.push(CREATE_GROUP)

        const taskWithGroup = Object.prototype.hasOwnProperty.call(selectedTask, 'group') && selectedTask.group

        if (taskWithGroup) {
          actions.push(REMOVE_FROM_GROUP)
        }
      }

      actions.push(CANCEL_MULTI)

    }

    if (containsOnlyUnassignedTasks) {
      actions.push(MOVE_TO_NEXT_DAY_MULTI)
      actions.push(MOVE_TO_NEXT_WORKING_DAY_MULTI)
      if (!containsOnlyGroupedTasks) {
        actions.push(ADD_TO_GROUP)
      }
    }
  }

  return (
    <Menu id="dashboard">
      <Item
        hidden={ !(actions.includes(UNASSIGN_SINGLE) && selectedTask) }
        onClick={ () => _unassign([ selectedTask ], unassignTasks) }
      >
        { selectedTask && t('ADMIN_DASHBOARD_UNASSIGN_TASK', { id: selectedTask.id }) }
      </Item>
      <Item
        hidden={ !(actions.includes(MOVE_TO_TOP) && selectedTask) }
        onClick={ () => moveToTop(selectedTask) }
      >
        { t('ADMIN_DASHBOARD_MOVE_TO_TOP') }
      </Item>
      <Item
        hidden={ !(actions.includes(MOVE_TO_BOTTOM) && selectedTask) }
        onClick={ () => moveToBottom(selectedTask) }
      >
        { t('ADMIN_DASHBOARD_MOVE_TO_BOTTOM') }
      </Item>
      <Item
        hidden={ !actions.includes(UNASSIGN_MULTI) }
        onClick={ () => _unassign(tasksToUnassign, unassignTasks) }
      >
        { t('ADMIN_DASHBOARD_UNASSIGN_TASKS_MULTI', { count: tasksToUnassign.length }) }
      </Item>
      <Item
        hidden={ !actions.includes(CANCEL_MULTI) }
        onClick={ () => cancelTasks(selectedTasks) }
      >
        { t('ADMIN_DASHBOARD_CANCEL_TASKS_MULTI', { count: selectedTasks.length }) }
      </Item>
      <Item
        hidden={ !actions.includes(MOVE_TO_NEXT_DAY_MULTI) }
        onClick={ () => moveTasksToNextDay(selectedTasks) }
      >
        { t('ADMIN_DASHBOARD_MOVE_TO_NEXT_DAY_MULTI', { count: selectedTasks.length }) }
      </Item>
      <Item
        hidden={ !actions.includes(MOVE_TO_NEXT_WORKING_DAY_MULTI) }
        onClick={ () => moveTasksToNextWorkingDay(selectedTasks) }
      >
        { t('ADMIN_DASHBOARD_MOVE_TO_NEXT_WORKING_DAY_MULTI', { count: selectedTasks.length, nextWorkingDay: moment(nextWorkingDay).format('LL') }) }
      </Item>
      <Item
        hidden={ !actions.includes(CREATE_GROUP) }
        onClick={ () => openCreateGroupModal() }
      >
        { t('ADMIN_DASHBOARD_CREATE_GROUP') }
      </Item>
      <Item
        hidden={ !actions.includes(ADD_TO_GROUP) }
        onClick={ () => openAddTaskToGroupModal(selectedTasks) }
      >
        { t('ADMIN_DASHBOARD_ADD_TO_GROUP') }
      </Item>
      <Item
        hidden={ !actions.includes(REMOVE_FROM_GROUP) }
        onClick={ () => removeTaskFromGroup(selectedTasks) }
      >
        { t('ADMIN_DASHBOARD_REMOVE_FROM_GROUP') }
      </Item>
      { actions.length === 0 && (
        <Item disabled>
          { t('ADMIN_DASHBOARD_NO_ACTION_AVAILABLE') }
        </Item>
      )}
    </Menu>
  )
}

function mapStateToProps(state) {

  return {
    unassignedTasks: selectUnassignedTasks(state),
    selectedTasks: selectSelectedTasks(state),
    nextWorkingDay: selectNextWorkingDay(state),
  }
}

function mapDispatchToProps(dispatch) {
  return {
    unassignTasks: (username, tasks) => dispatch(unassignTasks(username, tasks)),
    cancelTasks: tasks => dispatch(cancelTasks(tasks)),
    moveToTop: task => dispatch(moveToTop(task)),
    moveToBottom: task => dispatch(moveToBottom(task)),
    moveTasksToNextDay: tasks => dispatch(moveTasksToNextDay(tasks)),
    moveTasksToNextWorkingDay: tasks => dispatch(moveTasksToNextWorkingDay(tasks)),
    openCreateGroupModal: () => dispatch(openCreateGroupModal()),
    openAddTaskToGroupModal: tasks => dispatch(openAddTaskToGroupModal(tasks)),
    removeTaskFromGroup: tasks => dispatch(removeTaskFromGroup(tasks)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(DynamicMenu)
