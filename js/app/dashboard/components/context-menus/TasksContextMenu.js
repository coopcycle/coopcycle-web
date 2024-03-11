import React from 'react'
import {useDispatch, useSelector} from 'react-redux'
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
  restoreTasks,
  setCurrentTask,
  unassignTasks
} from '../../redux/actions'
import {selectLinkedTasksIds, selectNextWorkingDay, selectSelectedTasks} from '../../redux/selectors'
import {selectUnassignedTasks} from '../../../coopcycle-frontend-js/logistics/redux'

import 'react-contexify/dist/ReactContexify.css'
import { selectTaskIdToTourIdMap } from '../../../../shared/src/logistics/redux/selectors'

export const UNASSIGN_SINGLE = 'UNASSIGN_SINGLE'
export const UNASSIGN_MULTI = 'UNASSIGN_MULTI'
export const CANCEL_MULTI = 'CANCEL_MULTI'
export const MOVE_TO_TOP = 'MOVE_TO_TOP'
export const MOVE_TO_BOTTOM = 'MOVE_TO_BOTTOM'
export const MOVE_TO_NEXT_DAY_MULTI = 'MOVE_TO_NEXT_DAY_MULTI'
export const MOVE_TO_NEXT_WORKING_DAY_MULTI = 'MOVE_TO_NEXT_WORKING_DAY_MULTI'
export const CREATE_GROUP = 'CREATE_GROUP'
export const ADD_TO_GROUP = 'ADD_TO_GROUP'
export const REMOVE_FROM_GROUP = 'REMOVE_FROM_GROUP'
export const RESTORE = 'RESTORE'
export const RESCHEDULE = 'RESCHEDULE'
export const CREATE_DELIVERY = 'CREATE_DELIVERY'
export const CREATE_TOUR = 'CREATE_TOUR'

function _unassign(tasksToUnassign, unassignTasks) {
  const tasksByUsername = _.groupBy(tasksToUnassign, task => task.assignedTo)
  _.forEach(tasksByUsername, (tasks, username) => unassignTasks(username, tasks))
}

export function getAvailableActionsForTasks(selectedTasks, unassignedTasks, linkedTasksIds, selectedTasksBelongsToTour) {
  const tasksToUnassign =
  _.filter(selectedTasks, selectedTask =>
    !_.find(unassignedTasks, unassignedTask => unassignedTask['@id'] === selectedTask['@id']))

  const containsOnlyUnassignedTasks = !_.find(selectedTasks, t => t.isAssigned)
  const containsOnlyCancelledTasks = _.every(selectedTasks, t => t.status === 'CANCELLED')

  const containsOnlyGroupedTasks = selectedTasks.every(task => Object.prototype.hasOwnProperty.call(task, 'group') && task.group)
  const containsOnlyLinkedTasks = selectedTasks.every(task => linkedTasksIds.includes(task['@id']))

  const selectedTasksByType = _.countBy(selectedTasks, t => t.type)
  const containsOnePickupAndAtLeastOneDropoff = selectedTasksByType.PICKUP === 1 && selectedTasksByType.DROPOFF > 0

  if (selectedTasksBelongsToTour) {
    return []
  }

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
        actions.push(CREATE_TOUR)
      }

      if (containsOnlyGroupedTasks) {
        actions.push(REMOVE_FROM_GROUP)
      }

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
        actions.push(CREATE_TOUR)

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

  return actions
}

const DynamicMenu = () => {

  const { t } = useTranslation()

  const unassignedTasks = useSelector(selectUnassignedTasks)
  const selectedTasks = useSelector(selectSelectedTasks)
  const nextWorkingDay = useSelector(selectNextWorkingDay)
  const linkedTasksIds = useSelector(selectLinkedTasksIds)
  const taskIdToTourIdMap = useSelector(selectTaskIdToTourIdMap)
  const selectedTasksBelongsToTour = selectedTasks.includes(t => taskIdToTourIdMap.has(t['@id']))

  const actions = getAvailableActionsForTasks(selectedTasks, unassignedTasks, linkedTasksIds, selectedTasksBelongsToTour)

  const dispatch = useDispatch()

  const tasksToUnassign =
  _.filter(selectedTasks, selectedTask =>
    !_.find(unassignedTasks, unassignedTask => unassignedTask['@id'] === selectedTask['@id']))

  const selectedTask = selectedTasks.length > 0 ? selectedTasks[0] : undefined

  return (
    <Menu id="task-contextmenu">
      <Item
        hidden={ !(actions.includes(UNASSIGN_SINGLE) && selectedTask) }
        onClick={ () => _unassign([ selectedTask ], (username, tasks) => dispatch(unassignTasks(username, tasks))) }
      >
        { selectedTask && t('ADMIN_DASHBOARD_UNASSIGN_TASK', { id: selectedTask.id }) }
      </Item>
      <Item
        hidden={ !(actions.includes(MOVE_TO_TOP) && selectedTask) }
        onClick={ () => dispatch(moveToTop(selectedTask)) }
      >
        { t('ADMIN_DASHBOARD_MOVE_TO_TOP') }
      </Item>
      <Item
        hidden={ !(actions.includes(MOVE_TO_BOTTOM) && selectedTask) }
        onClick={ () => dispatch(moveToBottom(selectedTask)) }
      >
        { t('ADMIN_DASHBOARD_MOVE_TO_BOTTOM') }
      </Item>
      <Item
        hidden={ !actions.includes(UNASSIGN_MULTI) }
        onClick={ () => _unassign(tasksToUnassign, (username, tasks) => dispatch(unassignTasks(username, tasks))) }
      >
        { t('ADMIN_DASHBOARD_UNASSIGN_TASKS_MULTI', { count: tasksToUnassign.length }) }
      </Item>
      <Item
        hidden={ !actions.includes(CANCEL_MULTI) }
        onClick={ () => dispatch(cancelTasks(selectedTasks)) }
      >
        { t('ADMIN_DASHBOARD_CANCEL_TASKS_MULTI', { count: selectedTasks.length }) }
      </Item>
      <Item
        hidden={ !actions.includes(MOVE_TO_NEXT_DAY_MULTI) }
        onClick={ () => dispatch(moveTasksToNextDay(selectedTasks)) }
      >
        { t('ADMIN_DASHBOARD_MOVE_TO_NEXT_DAY_MULTI', { count: selectedTasks.length }) }
      </Item>
      <Item
        hidden={ !actions.includes(MOVE_TO_NEXT_WORKING_DAY_MULTI) }
        onClick={ () => dispatch(moveTasksToNextWorkingDay(selectedTasks)) }
      >
        { t('ADMIN_DASHBOARD_MOVE_TO_NEXT_WORKING_DAY_MULTI', { count: selectedTasks.length, nextWorkingDay: moment(nextWorkingDay).format('LL') }) }
      </Item>
      <Item
        hidden={ !actions.includes(CREATE_GROUP) }
        onClick={ () => dispatch(openCreateGroupModal()) }
      >
        { t('ADMIN_DASHBOARD_CREATE_GROUP') }
      </Item>
      <Item
        hidden={ !actions.includes(ADD_TO_GROUP) }
        onClick={ () => dispatch(openAddTaskToGroupModal(selectedTasks)) }
      >
        { t('ADMIN_DASHBOARD_ADD_TO_GROUP') }
      </Item>
      <Item
        hidden={ !actions.includes(REMOVE_FROM_GROUP) }
        onClick={ () => dispatch(removeTasksFromGroup(selectedTasks)) }
      >
        { t('ADMIN_DASHBOARD_REMOVE_FROM_GROUP') }
      </Item>
      <Item
        hidden={ !actions.includes(RESTORE) }
        onClick={ () => dispatch(restoreTasks(selectedTasks)) }
      >
        { t('ADMIN_DASHBOARD_RESTORE') }
      </Item>
      <Item
        hidden={ !actions.includes(RESCHEDULE) }
        onClick={ () => {
          dispatch(setCurrentTask(selectedTasks[0]))
          dispatch(openTaskRescheduleModal())
        }}
        >
        { t('ADMIN_DASHBOARD_RESCHEDULE') }
      </Item>
      <Item
        hidden={ !actions.includes(CREATE_DELIVERY) }
        onClick={ () => dispatch(openCreateDeliveryModal()) }
      >
        { t('ADMIN_DASHBOARD_CREATE_DELIVERY') }
      </Item>
      <Item
        hidden={ !actions.includes(CREATE_TOUR) }
        onClick={ () => dispatch(openCreateTourModal()) }
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

export default DynamicMenu
