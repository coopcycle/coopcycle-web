import React from 'react'
import {connect} from 'react-redux'
import _ from 'lodash'
import {useTranslation} from 'react-i18next'
import {Item, Menu} from 'react-contexify'

import moment from 'moment'

import {
  cancelTasks,
  moveTasksToNextDay,
  moveTasksToNextWorkingDay,
  moveToBottom,
  moveToTop,
  openAddTaskToGroupModal,
  openCreateDeliveryModal,
  openCreateGroupModal,
  openCreateTourModal,
  openTaskRescheduleModal,
  removeTasksFromGroup,
  restoreTasks, setCurrentTask,
  unassignTasks
} from '../redux/actions'
import {selectLinkedTasksIds, selectNextWorkingDay, selectSelectedTasks} from '../redux/selectors'
import {selectUnassignedTasks} from '../../coopcycle-frontend-js/logistics/redux'

import 'react-contexify/dist/ReactContexify.css'

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
const RESTORE = 'RESTORE'
const RESCHEDULE = 'RESCHEDULE'
const CREATE_DELIVERY = 'CREATE_DELIVERY'
const CREATE_TOUR = 'CREATE_TOUR'

function _unassign(tasksToUnassign, unassignTasks) {
  const tasksByUsername = _.groupBy(tasksToUnassign, task => task.assignedTo)
  _.forEach(tasksByUsername, (tasks, username) => unassignTasks(username, tasks))
}

const DynamicMenu = ({
  unassignedTasks, selectedTasks, setCurrentTask, nextWorkingDay, linkedTasksIds,
  unassignTasks, cancelTasks, moveToTop, moveToBottom, moveTasksToNextDay, moveTasksToNextWorkingDay,
  openCreateGroupModal, openAddTaskToGroupModal, removeTasksFromGroup, restoreTasks, openCreateDeliveryModal,
  openCreateTourModal, openTaskRescheduleModal
}) => {

  const { t } = useTranslation()

  const tasksToUnassign =
    _.filter(selectedTasks, selectedTask =>
      !_.find(unassignedTasks, unassignedTask => unassignedTask['@id'] === selectedTask['@id']))

  const containsOnlyUnassignedTasks = !_.find(selectedTasks, t => t.isAssigned)
  const containsOnlyCancelledTasks = _.every(selectedTasks, t => t.status === 'CANCELLED')

  const containsOnlyGroupedTasks = selectedTasks.every(task => Object.prototype.hasOwnProperty.call(task, 'group') && task.group)
  const containsOnlyLinkedTasks = selectedTasks.every(task => linkedTasksIds.includes(task['@id']))

  const selectedTasksByType = _.countBy(selectedTasks, t => t.type)
  const containsOnePickupAndAtLeastOneDropoff = selectedTasksByType.PICKUP === 1 && selectedTasksByType.DROPOFF > 0

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

      if (containsOnlyGroupedTasks) {
        actions.push(REMOVE_FROM_GROUP)
      }

      actions.push(CREATE_TOUR)

      if (containsOnePickupAndAtLeastOneDropoff) {
        if (!containsOnlyLinkedTasks) {
          actions.push(CREATE_DELIVERY)
        }
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

      if (selectedTask.status === 'FAILED' || selectedTask.status === 'CANCELLED') {
        actions.push(RESCHEDULE)
      }

    }

    if (containsOnlyUnassignedTasks) {
      actions.push(MOVE_TO_NEXT_DAY_MULTI)
      actions.push(MOVE_TO_NEXT_WORKING_DAY_MULTI)
      if (!containsOnlyGroupedTasks) {
        actions.push(ADD_TO_GROUP)
      }
    }

    if (containsOnlyCancelledTasks) {
      actions.push(RESTORE)
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
        onClick={ () => removeTasksFromGroup(selectedTasks) }
      >
        { t('ADMIN_DASHBOARD_REMOVE_FROM_GROUP') }
      </Item>
      <Item
        hidden={ !actions.includes(RESTORE) }
        onClick={ () => restoreTasks(selectedTasks) }
      >
        { t('ADMIN_DASHBOARD_RESTORE') }
      </Item>
      <Item
        hidden={ !actions.includes(RESCHEDULE) }
        onClick={ () => {
          setCurrentTask(selectedTasks[0])
          openTaskRescheduleModal()
        }}
        >
        { t('ADMIN_DASHBOARD_RESCHEDULE') }
      </Item>
      <Item
        hidden={ !actions.includes(CREATE_DELIVERY) }
        onClick={ () => openCreateDeliveryModal() }
      >
        { t('ADMIN_DASHBOARD_CREATE_DELIVERY') }
      </Item>
      <Item
        hidden={ !actions.includes(CREATE_TOUR) }
        onClick={ () => openCreateTourModal() }
      >
        { t('ADMIN_DASHBOARD_CREATE_TOUR') }
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
    linkedTasksIds: selectLinkedTasksIds(state),
  }
}

function mapDispatchToProps(dispatch) {
  return {
    unassignTasks: (username, tasks) => dispatch(unassignTasks(username, tasks)),
    setCurrentTask: (task) => dispatch(setCurrentTask(task)),
    cancelTasks: tasks => dispatch(cancelTasks(tasks)),
    moveToTop: task => dispatch(moveToTop(task)),
    moveToBottom: task => dispatch(moveToBottom(task)),
    moveTasksToNextDay: tasks => dispatch(moveTasksToNextDay(tasks)),
    moveTasksToNextWorkingDay: tasks => dispatch(moveTasksToNextWorkingDay(tasks)),
    openCreateGroupModal: () => dispatch(openCreateGroupModal()),
    openAddTaskToGroupModal: tasks => dispatch(openAddTaskToGroupModal(tasks)),
    removeTasksFromGroup: tasks => dispatch(removeTasksFromGroup(tasks)),
    restoreTasks: tasks => dispatch(restoreTasks(tasks)),
    openCreateDeliveryModal: () => dispatch(openCreateDeliveryModal()),
    openCreateTourModal: () => dispatch(openCreateTourModal()),
    openTaskRescheduleModal: () => dispatch(openTaskRescheduleModal()),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(DynamicMenu)
