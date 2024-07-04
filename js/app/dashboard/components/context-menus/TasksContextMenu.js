import React, { useEffect } from 'react'
import {useDispatch, useSelector} from 'react-redux'
import _ from 'lodash'
import {useTranslation} from 'react-i18next'
import {Item, Menu, Submenu, Separator, useContextMenu} from 'react-contexify'

import moment from 'moment'

import {
  cancelTasks,
  createTaskList,
  modifyTaskList,
  moveTasksToNextDay,
  moveTasksToNextWorkingDay,
  moveToBottom,
  moveToTop,
  openAddTaskToGroupModal,
  openCreateDeliveryModal,
  openCreateGroupModal,
  openCreateTourModal,
  openReportIncidentModal,
  openTaskRescheduleModal,
  openTaskTaskList,
  removeTasksFromGroup,
  restoreTasks,
  setCurrentTask,
  setTaskToShow,
  startTasks,
  toggleTasksGroupPanelExpanded,
  toggleTourPanelExpanded,
  unassignTasks
} from '../../redux/actions'
import {selectCouriersWithExclude, selectExpandedTasksGroupsPanelsIds, selectExpandedTourPanelsIds, selectLinkedTasksIds, selectNextWorkingDay, selectSelectedTasks, selectTaskListsLoading, selectTaskToShow} from '../../redux/selectors'
import {selectUnassignedTasks} from '../../../coopcycle-frontend-js/logistics/redux'

import 'react-contexify/dist/ReactContexify.css'
import { selectAllTasks, selectSelectedDate, selectTaskIdToTourIdMap, taskListSelectors } from '../../../../shared/src/logistics/redux/selectors'
import { isValidTasksMultiSelect, usePrevious, withOrderTasksForDragNDrop } from '../../redux/utils'
import Avatar from '../../../components/Avatar'

export const ASSIGN_MULTI = 'ASSIGN_MULTI'
export const ASSIGN_WITH_LINKED_TASKS_MULTI = 'ASSIGN_WITH_LINKED_TASKS_MULTI'
export const UNASSIGN_SINGLE = 'UNASSIGN_SINGLE'
export const UNASSIGN_MULTI = 'UNASSIGN_MULTI'
export const CANCEL_MULTI = 'CANCEL_MULTI'
export const MOVE_TO_TOP = 'MOVE_TO_TOP'
export const MOVE_TO_BOTTOM = 'MOVE_TO_BOTTOM'
export const MOVE_TO_NEXT_DAY_MULTI = 'MOVE_TO_NEXT_DAY_MULTI'
export const MOVE_TO_NEXT_WORKING_DAY_MULTI = 'MOVE_TO_NEXT_WORKING_DAY_MULTI'
export const START_TASKS_MULTI = 'START_TASKS_MULTI'
export const CREATE_GROUP = 'CREATE_GROUP'
export const ADD_TO_GROUP = 'ADD_TO_GROUP'
export const REMOVE_FROM_GROUP = 'REMOVE_FROM_GROUP'
export const RESTORE = 'RESTORE'
export const RESCHEDULE = 'RESCHEDULE'
export const CREATE_DELIVERY = 'CREATE_DELIVERY'
export const CREATE_TOUR = 'CREATE_TOUR'
export const REPORT_INCIDENT = 'REPORT_INCIDENT'

const { hideAll } = useContextMenu({
  id: 'task-contextmenu',
})

const useAssignAction = function() {
  const dispatch = useDispatch()
  const date = useSelector(selectSelectedDate)
  const taskLists = useSelector(taskListSelectors.selectAll)

  return async function (username, tasksToAssign) {
    let tasksList = taskLists.find(tl => tl.username === username)

    if (!tasksList) {
      tasksList= await dispatch(createTaskList(date, username))
    }

    const newTasksList = [...tasksList.items, ...tasksToAssign.map(t => t['@id'])]
    return dispatch(modifyTaskList(tasksList.username, newTasksList))
  }
}

function _unassign(tasksToUnassign, unassignTasks) {
  const tasksByUsername = _.groupBy(tasksToUnassign, task => task.assignedTo) // legacy : work with tasks selected from several riders, but we limited this case with isValidTasksMultiSelect
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

      actions.push(START_TASKS_MULTI)

      if (tasksToUnassign.length > 0) {
        actions.push(UNASSIGN_MULTI)
      }

      if (containsOnlyUnassignedTasks) {
        actions.push(ASSIGN_MULTI)
        actions.push(ASSIGN_WITH_LINKED_TASKS_MULTI)
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
        actions.push(ASSIGN_MULTI)
        actions.push(ASSIGN_WITH_LINKED_TASKS_MULTI)
        actions.push(CREATE_GROUP)
        actions.push(CREATE_TOUR)

        const taskWithGroup = Object.prototype.hasOwnProperty.call(selectedTask, 'group') && selectedTask.group

        if (taskWithGroup) {
          actions.push(REMOVE_FROM_GROUP)
        }
      }

      actions.push(CANCEL_MULTI)
      actions.push(START_TASKS_MULTI)
      actions.push(RESCHEDULE)
      actions.push(REPORT_INCIDENT)

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

  const allTasks = useSelector(selectAllTasks)
  const selectedTasks = useSelector(selectSelectedTasks)
  const unassignedTasks = useSelector(selectUnassignedTasks)

  const nextWorkingDay = useSelector(selectNextWorkingDay)
  const linkedTasksIds = useSelector(selectLinkedTasksIds)
  const taskIdToTourIdMap = useSelector(selectTaskIdToTourIdMap)
  const selectedTasksBelongsToTour = selectedTasks.some(t => taskIdToTourIdMap.has(t['@id']))
  const isValidMultiselect = isValidTasksMultiSelect(selectedTasks, taskIdToTourIdMap)
  const couriers = useSelector(selectCouriersWithExclude)
  const tasksListsLoading = useSelector(selectTaskListsLoading)

  // selectors to handle the showing of selected tasks
  const previouslySelectedTasks = usePrevious(selectedTasks || [])
  const expandedTourPanelsIds = useSelector(selectExpandedTourPanelsIds)
  const expandedGroupPanelsIds = useSelector(selectExpandedTasksGroupsPanelsIds)
  const taskToShow = useSelector(selectTaskToShow)

  let selectedOrders =  withOrderTasksForDragNDrop(selectedTasks, allTasks, taskIdToTourIdMap)

  const assign = useAssignAction()
  const assignSelectedOrders = (username) => assign(username, selectedOrders)
  const assignSelectedTasks = (username) => assign(username, selectedTasks)

  const actions = getAvailableActionsForTasks(selectedTasks, unassignedTasks, linkedTasksIds, selectedTasksBelongsToTour)

  const dispatch = useDispatch()

  useEffect(() => {
    const newSelectedTasks = _.differenceBy(selectedTasks, previouslySelectedTasks, '@id')

    if (newSelectedTasks.length > 0) {
      const taskToShow = newSelectedTasks[0]

      newSelectedTasks.forEach(task => {
        // if task is in a closed tour, open the tour
        if (taskIdToTourIdMap.has(task['@id']) && !expandedTourPanelsIds.includes(taskIdToTourIdMap.get(task['@id']))) {
          dispatch(toggleTourPanelExpanded(taskIdToTourIdMap.get(task['@id'])))
        }

        if (task.group && !task.isAssigned && !expandedGroupPanelsIds.includes(task.group['@id'])) {
          dispatch(toggleTasksGroupPanelExpanded(task.group['@id']))
        }

        if (task.isAssigned) {
          dispatch(openTaskTaskList(task))
        }
      })

      dispatch(setTaskToShow(taskToShow))

    }
  }, [selectedTasks])

  useEffect(() => {
    if(taskToShow) {
      requestAnimationFrame(() => {
        // detects if the 'task to show' is visible to the user
        // it is quite fragile as it depends on the layout
        const htmlEl = document.querySelector(`.dashboard__aside [data-task-id="${taskToShow['@id']}"]`)
        const rect = htmlEl.getBoundingClientRect()

        const parentPanel = $(htmlEl).closest('.dashboard__panel')[0]
        const parentRect = parentPanel.getBoundingClientRect()

        if (!(rect.top >= parentRect.top - 20 && rect.bottom <= parentRect.bottom + 20)) {
          htmlEl.scrollIntoView()
        }
    })
    }
  }, [taskToShow])

  const tasksToUnassign =
  _.filter(selectedTasks, selectedTask =>
    !_.find(unassignedTasks, unassignedTask => unassignedTask['@id'] === selectedTask['@id']))

  const selectedTask = selectedTasks.length > 0 ? selectedTasks[0] : undefined

  return (
    <Menu id="task-contextmenu">
      { isValidMultiselect ?
      <>
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
      <Submenu label={t('ADMIN_DASHBOARD_ASSIGN_TASKS', { count: selectedTasks.length })} hidden={ !actions.includes(ASSIGN_MULTI)}>
        { tasksListsLoading
          ? (<div className="text-center"><span className="loader loader--dark"></span>&nbsp;{t('ADMIN_DASHBOARD_WAIT_FOR_PREVIOUS_ASSIGN')}</div>)
          : couriers.map((c) =>
            <Item key={c.username} onClick={() => {
                // hide manually menu and submenu
                // https://github.com/fkhadra/react-contexify/issues/172
                hideAll()
                assignSelectedTasks(c.username)
            }}>
              <Avatar username={c.username} />  {c.username}
            </Item>
        )}
      </Submenu>
      <Submenu label={t('ADMIN_DASHBOARD_ASSIGN_DELIVERIES_ORDERS', { count: selectedTasks.length })} hidden={ !actions.includes(ASSIGN_WITH_LINKED_TASKS_MULTI)}>
      { tasksListsLoading
        ? (<div className="text-center"><span className="loader loader--dark"></span>&nbsp;{t('ADMIN_DASHBOARD_WAIT_FOR_PREVIOUS_ASSIGN')}</div>)
        : couriers.map((c) =>
          <Item key={c.username} onClick={() => {
            // hide manually menu and submenu
            // https://github.com/fkhadra/react-contexify/issues/172
            hideAll()
            assignSelectedOrders(c.username)
        }}>
            <Avatar username={c.username} />  {c.username}
          </Item>
      )}
      </Submenu>
     <Separator />
      <Item
        hidden={ !actions.includes(CANCEL_MULTI) }
        onClick={ () => dispatch(cancelTasks(selectedTasks)) }
      >
        { t('ADMIN_DASHBOARD_CANCEL_TASKS_MULTI', { count: selectedTasks.length }) }
      </Item>
      <Item
        hidden={!actions.includes(START_TASKS_MULTI)}
        onClick={() => dispatch(startTasks(selectedTasks))}
      >
        {t('ADMIN_DASHBOARD_START_TASKS_MULTI', {count: selectedTasks.length})}
      </Item>
      <Separator />
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
      <Item
            hidden={ !actions.includes(REPORT_INCIDENT) }
            onClick={ () => dispatch(openReportIncidentModal()) }
          >
            {t("ADMIN_DASHBOARD_REPORT_INCIDENT")}
      </Item>
      { selectedTasks.length > 0 && actions.length === 0 && (
        <Item disabled>
          { t('ADMIN_DASHBOARD_NO_ACTION_AVAILABLE') }
        </Item>
      )}
      { selectedTasks.length === 0 && (
        <Item disabled>
          { t('ADMIN_DASHBOARD_NO_SELECTED_TASKS') }
        </Item>
      )}
      </> : <Item disabled={true}>{t('ADMIN_DASHBOARD_INVALID_TASKS_SELECTION')}</Item> }
    </Menu>
  )
}

export default DynamicMenu
