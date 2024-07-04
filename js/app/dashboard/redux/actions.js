import _ from 'lodash'
import moment from 'moment'

import { taskComparator, isInDateRange, withoutItemsIRIs } from './utils'
import {
  selectSelectedDate,
  createTaskListRequest,
  createTaskListSuccess,
  createTaskListFailure
} from '../../coopcycle-frontend-js/logistics/redux'
import { selectExpandedTaskListPanelsIds, selectNextWorkingDay, selectSelectedTasks, selectTaskLists } from './selectors'
import { createAction } from '@reduxjs/toolkit'
import { selectTaskById, selectTaskListByUsername } from '../../../shared/src/logistics/redux/selectors'
import { createClient } from '../utils/client'

export const UPDATE_TASK = 'UPDATE_TASK'
export const OPEN_ADD_USER = 'OPEN_ADD_USER'
export const CLOSE_ADD_USER = 'CLOSE_ADD_USER'
export const MODIFY_TASK_LIST_REQUEST = 'MODIFY_TASK_LIST_REQUEST'
export const MODIFY_TASK_LIST_REQUEST_SUCCESS = 'MODIFY_TASK_LIST_REQUEST_SUCCESS'
export const TASK_LISTS_UPDATED = 'TASK_LISTS_UPDATED'
export const TOGGLE_POLYLINE = 'TOGGLE_POLYLINE'
export const TOGGLE_TASK = 'TOGGLE_TASK'
export const SELECT_TASK = 'SELECT_TASK'
export const SELECT_TASKS = 'SELECT_TASKS'
export const CLEAR_SELECTED_TASKS = 'CLEAR_SELECTED_TASKS'
export const SET_TASK_LIST_GROUP_MODE = 'SET_TASK_LIST_GROUP_MODE'
export const REMOVE_TASK = 'REMOVE_TASK'

export const SET_GEOLOCATION = 'SET_GEOLOCATION'
export const SCAN_POSITIONS = 'SCAN_POSITIONS'
export const OPEN_NEW_TASK_MODAL = 'OPEN_NEW_TASK_MODAL'
export const CLOSE_NEW_TASK_MODAL = 'CLOSE_NEW_TASK_MODAL'
export const SET_CURRENT_TASK = 'SET_CURRENT_TASK'
export const CREATE_TASK_REQUEST = 'CREATE_TASK_REQUEST'
export const CREATE_TASK_SUCCESS = 'CREATE_TASK_SUCCESS'
export const CREATE_TASK_FAILURE = 'CREATE_TASK_FAILURE'
export const COMPLETE_TASK_FAILURE = 'COMPLETE_TASK_FAILURE'
export const CANCEL_TASK_FAILURE = 'CANCEL_TASK_FAILURE'
export const RESTORE_TASK_FAILURE = 'RESTORE_TASK_FAILURE'

export const OPEN_FILTERS_MODAL = 'OPEN_FILTERS_MODAL'
export const CLOSE_FILTERS_MODAL = 'CLOSE_FILTERS_MODAL'
export const SET_FILTER_VALUE = 'SET_FILTER_VALUE'
export const RESET_FILTERS = 'RESET_FILTERS'

export const OPEN_SETTINGS = 'OPEN_SETTINGS'
export const CLOSE_SETTINGS = 'CLOSE_SETTINGS'

export const LOAD_TASK_EVENTS_REQUEST = 'LOAD_TASK_EVENTS_REQUEST'
export const LOAD_TASK_EVENTS_SUCCESS = 'LOAD_TASK_EVENTS_SUCCESS'
export const LOAD_TASK_EVENTS_FAILURE = 'LOAD_TASK_EVENTS_FAILURE'

export const ADD_IMPORT = 'ADD_IMPORT'
export const IMPORT_SUCCESS = 'IMPORT_SUCCESS'
export const IMPORT_ERROR = 'IMPORT_ERROR'
export const OPEN_IMPORT_MODAL = 'OPEN_IMPORT_MODAL'
export const CLOSE_IMPORT_MODAL = 'CLOSE_IMPORT_MODAL'

export const OPEN_EXPORT_MODAL = 'OPEN_EXPORT_MODAL'
export const CLOSE_EXPORT_MODAL = 'CLOSE_EXPORT_MODAL'

export const OPTIMIZE_TASK_LIST = 'OPTIMIZE_TASK_LIST'

export const RIGHT_PANEL_MORE_THAN_HALF = 'RIGHT_PANEL_MORE_THAN_HALF'
export const RIGHT_PANEL_LESS_THAN_HALF = 'RIGHT_PANEL_LESS_THAN_HALF'

export const OPEN_RECURRENCE_RULE_MODAL = 'OPEN_RECURRENCE_RULE_MODAL'
export const CLOSE_RECURRENCE_RULE_MODAL = 'CLOSE_RECURRENCE_RULE_MODAL'
export const SET_CURRENT_RECURRENCE_RULE = 'SET_CURRENT_RECURRENCE_RULE'
export const UPDATE_RECURRENCE_RULE_REQUEST = 'UPDATE_RECURRENCE_RULE_REQUEST'
export const UPDATE_RECURRENCE_RULE_SUCCESS = 'UPDATE_RECURRENCE_RULE_SUCCESS'
export const DELETE_RECURRENCE_RULE_SUCCESS = 'DELETE_RECURRENCE_RULE_SUCCESS'
export const UPDATE_RECURRENCE_RULE_ERROR = 'UPDATE_RECURRENCE_RULE_ERROR'
export const SHOW_RECURRENCE_RULES = 'SHOW_RECURRENCE_RULES'

export const DELETE_GROUP_SUCCESS = 'DELETE_GROUP_SUCCESS'
export const EDIT_GROUP_SUCCESS = 'EDIT_GROUP_SUCCESS'

export const OPEN_CREATE_GROUP_MODAL = 'OPEN_CREATE_GROUP_MODAL'
export const CLOSE_CREATE_GROUP_MODAL = 'CLOSE_CREATE_GROUP_MODAL'

export const OPEN_REPORT_INCIDENT_MODAL = 'OPEN_REPORT_INCIDENT_MODAL'
export const CLOSE_REPORT_INCIDENT_MODAL = 'CLOSE_REPORT_INCIDENT_MODAL'

export const OPEN_ADD_TASK_TO_GROUP_MODAL = 'OPEN_ADD_TASK_TO_GROUP_MODAL'
export const CLOSE_ADD_TASK_TO_GROUP_MODAL = 'CLOSE_ADD_TASK_TO_GROUP_MODAL'
export const ADD_TASK_TO_GROUP_REQUEST = 'ADD_TASK_TO_GROUP_REQUEST'
export const ADD_TASKS_TO_GROUP_SUCCESS = 'ADD_TASKS_TO_GROUP_SUCCESS'

export const REMOVE_TASK_FROM_GROUP_REQUEST = 'REMOVE_TASK_FROM_GROUP_REQUEST'
export const REMOVE_TASKS_FROM_GROUP_SUCCESS = 'REMOVE_TASKS_FROM_GROUP_SUCCESS'

export const CREATE_GROUP_REQUEST = 'CREATE_GROUP_REQUEST'
export const CREATE_GROUP_SUCCESS = 'CREATE_GROUP_SUCCESS'

export const OPEN_CREATE_DELIVERY_MODAL = 'OPEN_CREATE_DELIVERY_MODAL'
export const CLOSE_CREATE_DELIVERY_MODAL = 'CLOSE_CREATE_DELIVERY_MODAL'

export const OPEN_CREATE_TOUR_MODAL = 'OPEN_CREATE_TOUR_MODAL'
export const CLOSE_CREATE_TOUR_MODAL = 'CLOSE_CREATE_TOUR_MODAL'

export const OPEN_TASK_RESCHEDULE_MODAL = 'OPEN_TASK_RESCHEDULE_MODAL'
export const CLOSE_TASK_RESCHEDULE_MODAL = 'CLOSE_TASK_RESCHEDULE_MODAL'

export const CREATE_TOUR_REQUEST = 'CREATE_TOUR_REQUEST'
export const CREATE_TOUR_REQUEST_SUCCESS = 'CREATE_TOUR_REQUEST_SUCCESS'
export const MODIFY_TOUR_REQUEST = 'MODIFY_TOUR_REQUEST'
export const MODIFY_TOUR_REQUEST_SUCCESS = 'MODIFY_TOUR_REQUEST_SUCCESS'
export const MODIFY_TOUR_REQUEST_ERROR = 'MODIFY_TOUR_REQUEST_ERROR'
export const TOGGLE_TOUR_LOADING = 'TOGGLE_TOUR_LOADING'
export const UPDATE_TOUR = 'UPDATE_TOUR'
export const DELETE_TOUR_SUCCESS = 'DELETE_TOUR_SUCCESS'

export const INSERT_IN_UNASSIGNED_TOURS = 'INSERT_IN_UNASSIGNED_TOURS'

export const SET_TOURS_ENABLED = 'SET_TOURS_ENABLED'

export const setUnassignedTasksLoading = createAction('SET_UNASSIGNEDTASKS_LOADING')
export const appendToUnassignedTasks = createAction('APPEND_TO_UNASSIGNED_TASKS')
export const insertInUnassignedTasks = createAction('INSERT_IN_UNASSIGNED_TASKS')
export const appendToUnassignedTours = createAction('APPEND_TO_UNASSIGNED_TOURS')
export const insertInUnassignedTours = createAction('INSERT_IN_UNASSIGNED_TOURS')

export const startTaskFailure = createAction('START_TASK_FAILURE')

export const loadOrganizationsSuccess = createAction('LOAD_ORGANIZATIONS_SUCCESS')

export const toggleTourPanelExpanded = createAction('TOGGLE_TOUR_PANEL_EXPANDED')
export const toggleTaskListPanelExpanded = createAction('TASKLIST_PANEL_EXPANDED')
export const toggleTasksGroupPanelExpanded = createAction('TASKS_GROUP_PANEL_EXPANDED')
export const setTaskToShow = createAction('SET_TASK_TO_SHOW')

export const openTaskTaskList = function(task) {
  return function(dispatch, getState) {
    if (task.isAssigned) {
      const taskList = selectTaskListByUsername(getState(), {username: task.assignedTo})
      const expandedTaskListPanelsIds = selectExpandedTaskListPanelsIds(getState())
      if (!expandedTaskListPanelsIds.includes(taskList['@id'])) {
        dispatch(toggleTaskListPanelExpanded(taskList['@id']))
      }
    }
  }
}

/**
 * This action assign a task after another when you linked the two markers on the map
 * @param {string} username - Username of the rider to which we assign
 * @param {Object} task - Task we want to assign after the "after" task
 * @param {Object} task - Task pointed on the map
 */
export function assignAfter(username, task, after) {

  return function(dispatch, getState) {

    let state = getState()
    let taskLists = selectTaskLists(state)

    const taskList = _.find(taskLists, taskList => taskList.username === username)
    const taskIndex = _.findIndex(
      taskList.items,
      t => taskComparator(selectTaskById(getState(), t), after['@id'])
    )

    if (-1 !== taskIndex) {
      const newTaskListItems = taskList.items.slice()
      Array.prototype.splice.apply(newTaskListItems,
        Array.prototype.concat([ taskIndex + 1, 0 ], task['@id'])
      )
      dispatch(modifyTaskList(username, newTaskListItems))
    }
  }
}

/**
 * Unassign tasks or tours
 * @param {string} username - Username of the rider
 * @param {Array.Object} items - Items (tasks or tours) to be unassigned
 */
export function unassignTasks(username, items) {

  if (!Array.isArray(items)) {
    items = [ items ]
  }

  return async function(dispatch, getState) {

    if (items.length === 0) {
      return
    }

    const taskList = selectTaskListByUsername(getState(), {username: username}),
      toRemove = items.map(i => i['@id'])

    await dispatch(modifyTaskList(username, withoutItemsIRIs(taskList.items, toRemove)))
  }
}

export function _updateTask(task) {
  return {type: UPDATE_TASK, task}
}

export function openAddUserModal() {
  return {type: OPEN_ADD_USER}
}

export function closeAddUserModal() {
  return {type: CLOSE_ADD_USER}
}

/**
 * @param {string} Username - Username of the rider to which we assign
 * @param {Array.string} items - Items to be assigned, list of tasks and tours URIs to be assigned
 * @param {Array.string} previousItems - Items to be assigned, list of tasks and tours URIs to be assigned
 */
export function modifyTaskListRequest(username, items, previousItems) {
  return { type: MODIFY_TASK_LIST_REQUEST, username, items, previousItems }
}

export function modifyTaskListRequestSuccess(taskList) {
  return { type: MODIFY_TASK_LIST_REQUEST_SUCCESS, taskList }
}

export function setFilterValue(key, value) {
  return { type: SET_FILTER_VALUE, key, value }
}

export function resetFilters() {
  return { type: RESET_FILTERS }
}

export function addImport(token) {
  return { type: ADD_IMPORT, token }
}

export function importSuccess(token) {
  return { type: IMPORT_SUCCESS, token }
}

export function importError(token, message) {
  return { type: IMPORT_ERROR, token, message }
}

/**
 * Modify a TaskList
 * @param {string} Username - Username of the rider to which we assign
 * @param {Array.Objects} items - Items to be assigned, list of tasks and tours to be assigned
 */
export function modifyTaskList(username, items) {

  return async function(dispatch, getState) {

    const state = getState()

    const tasksList = selectTaskListByUsername(getState(), {username: username})
    const previousItems = tasksList.items

    // support passing URIs directly - TODO uniformize behaviour
    const newItems = items.map((item) => item['@id'] || item)

    dispatch(modifyTaskListRequest(username, newItems, previousItems))

    const date = selectSelectedDate(state)

    const url = window.Routing.generate('api_task_lists_set_items_item', {
      date: date.format('YYYY-MM-DD'),
      username,
    })

    const { jwt } = getState()
    const httpClient = createClient(dispatch)

    let response

    try {
      response = await httpClient.request({
        method: 'put',
        url,
        data: {'items': newItems},
        headers: {
          'Authorization': `Bearer ${jwt}`,
          'Accept': 'application/ld+json',
          'Content-Type': 'application/ld+json'
        }
      })
    } catch (error) {
      // eslint-disable-next-line no-console
      console.error(error)
    }

    dispatch(modifyTaskListRequestSuccess(response.data))
    return response.data
  }
}

export function togglePolyline(username) {
  return { type: TOGGLE_POLYLINE, username }
}
export const toggleTourPolyline = createAction('TOGGLE_TOUR_POLYLINE')

export function taskListsUpdated(taskList) {
  return { type: TASK_LISTS_UPDATED, taskList }
}

export function toggleTask(task, multiple = false) {
  /*
    Toggle the given task in the `selectedTasks` list.

    Check the `TOGGLE_TASK` reducer for the exact behavior. Pass the `multiple` flag if you want to keep the already selected tasks in the list.
  */
  return { type: TOGGLE_TASK, taskId: task['@id'], multiple }
}

export function selectTask(task) {
  return { type: SELECT_TASK, taskId: task['@id'] }
}

export function selectTasksByIds(taskIds) {
  /*
    Set selectedTasks to the given `taskIds`.
  */
  return { type: SELECT_TASKS, taskIds }
}

export function clearSelectedTasks() {
  return { type: CLEAR_SELECTED_TASKS }
}

export function setTaskListGroupMode(mode) {
  return { type: SET_TASK_LIST_GROUP_MODE, mode }
}

export function createTaskList(date, username) {

  return async function(dispatch) {

    const url = window.Routing.generate('admin_task_list_create', {
      date: date.format('YYYY-MM-DD'),
      username
    })

    dispatch(createTaskListRequest())

    let response
    try {
      response =  await createClient(dispatch).post(url, {}, {
        withCredentials: true,
        headers: {
          'Content-Type': 'application/ld+json'
        },
      })
    } catch (error) {
      // eslint-disable-next-line no-console
      dispatch(createTaskListFailure(error))
    }

    dispatch(createTaskListSuccess(response.data))
    return response.data
  }
}

/**
 * Action to move task to top or bottom of tasklist
 * @param {Object} task - Task we are moving
 * @param {string} direction - Either 'top' or 'bottom'
 */
function moveTo(task, direction) {

  return function(dispatch, getState) {

    const taskLists = selectTaskLists(getState())
    const taskList = _.find(taskLists, taskList => taskList.username === task.assignedTo)

    if (taskList) {
      const taskId = task['@id'],
        newItems = taskList.items.filter(t => t !== taskId)
      switch (direction) {
        case 'top':
          newItems.unshift(taskId)
          break
        case 'bottom':
          newItems.push(taskId)
          break
      }
      dispatch(modifyTaskList(taskList.username, newItems))
    }
  }
}

export function moveToTop(task) {

  return function(dispatch) {

    dispatch(moveTo(task, 'top'))
  }
}

export function moveToBottom(task) {

  return function(dispatch) {

    dispatch(moveTo(task, 'bottom'))
  }
}

export function setGeolocation(username, coords, timestamp) {
  return { type: SET_GEOLOCATION, username, coords, timestamp }
}

export function scanPositions() {
  return { type: SCAN_POSITIONS }
}

export function openNewTaskModal() {
  return { type: OPEN_NEW_TASK_MODAL }
}

export function closeNewTaskModal() {
  return { type: CLOSE_NEW_TASK_MODAL }
}

export function setCurrentTask(task) {
  return { type: SET_CURRENT_TASK, task }
}

export function createTaskRequest() {
  return { type: CREATE_TASK_REQUEST }
}

export function createTaskSuccess(task) {
  return { type: CREATE_TASK_SUCCESS, task }
}

export function createTaskFailure(error) {
  return { type: CREATE_TASK_FAILURE, error }
}

export function restoreTaskFailure(error) {
  return { type: RESTORE_TASK_FAILURE, error }
}

export function completeTaskFailure(error) {
  return { type: COMPLETE_TASK_FAILURE, error }
}

export function cancelTaskFailure(error) {
  return { type: CANCEL_TASK_FAILURE, error }
}

export function openFiltersModal() {
  return { type: OPEN_FILTERS_MODAL }
}

export function closeFiltersModal() {
  return { type: CLOSE_FILTERS_MODAL }
}

export function openSettings() {
  return { type: OPEN_SETTINGS }
}

export function closeSettings() {
  return { type: CLOSE_SETTINGS }
}

export const setGeneralSettings = createAction('SET_FROM_SETTING_MODAL')

export function loadTaskEventsRequest() {
  return { type: LOAD_TASK_EVENTS_REQUEST }
}

export function loadTaskEventsSuccess(task, events) {
  return { type: LOAD_TASK_EVENTS_SUCCESS, task, events }
}

export function loadTaskEventsFailure(error) {
  return { type: LOAD_TASK_EVENTS_FAILURE, error }
}

export function openImportModal() {
  return { type: OPEN_IMPORT_MODAL }
}

export function showRecurrenceRules(isChecked) {
  return { type: SHOW_RECURRENCE_RULES, isChecked }
}

export function closeImportModal() {
  return { type: CLOSE_IMPORT_MODAL }
}

export function removeTask(task) {
  return { type: REMOVE_TASK, task }
}

export function updateTask(task) {
  return function(dispatch, getState) {
    let date = selectSelectedDate(getState())

    if (isInDateRange(task, date)) {
      dispatch(_updateTask(task))
    } else {
      dispatch(removeTask(task))
    }
  }
}

export function updateTour(tour) {
  return {type: UPDATE_TOUR, tour}
}

export function createTask(task) {

  return function(dispatch, getState) {

    const { jwt } = getState()

    dispatch(createTaskRequest())

    const data = {
      ...task,
      doneAfter: task.after,
      doneBefore: task.before,
      tags: _.map(task.tags, tag => tag.slug)
    }

    const url = Object.prototype.hasOwnProperty.call(task, '@id') ? task['@id'] : '/api/tasks'
    const method = Object.prototype.hasOwnProperty.call(task, '@id') ? 'put' : 'post'

    const payload = _.omit(data, [
      '@context',
      '@id',
      '@type',
      'events',
      'isAssigned',
      'id',
      'status',
      'updatedAt',
      'images',
    ])

    createClient(dispatch).request({
      method,
      url,
      data: payload,
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      }
    })
      .then(response => {
        dispatch(createTaskSuccess())
        dispatch(updateTask(response.data))
        dispatch(closeNewTaskModal())
      })
      .catch(error => dispatch(createTaskFailure(error)))
  }
}

export function startTask(task) {

  return function(dispatch, getState) {

    const { jwt } = getState()

    dispatch(createTaskRequest())

    const url = task['@id'] + '/start'

    createClient(dispatch).request({
      method: 'put',
      url,
      data: {},
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      }
    })
      .then(response => {
        dispatch(createTaskSuccess())
        dispatch(updateTask(response.data))
        dispatch(closeNewTaskModal())
      })
      .catch(error => dispatch(completeTaskFailure(error)))
  }
}

export function completeTask(task, notes = '', success = true) {

  return function(dispatch, getState) {

    const { jwt } = getState()

    dispatch(createTaskRequest())

    const url = task['@id'] + (success ? '/done' : '/failed')

    createClient(dispatch).request({
      method: 'put',
      url,
      data: { notes },
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      }
    })
      .then(response => {
        dispatch(createTaskSuccess())
        dispatch(updateTask(response.data))
        dispatch(closeNewTaskModal())
      })
      .catch(error => dispatch(completeTaskFailure(error)))
  }
}

export function cancelTask(task) {

  return function(dispatch, getState) {

    const { jwt } = getState()

    dispatch(createTaskRequest())

    const url = `${task['@id']}/cancel`

    createClient(dispatch).request({
      method: 'put',
      url,
      data: {},
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      }
    })
      .then(response => {
        dispatch(createTaskSuccess())
        dispatch(updateTask(response.data))
        dispatch(closeNewTaskModal())
      })
      .catch(error => dispatch(cancelTaskFailure(error)))
  }
}

export function cancelTasks(tasks) {

  return function(dispatch, getState) {

    const { jwt } = getState()

    dispatch(createTaskRequest())

    const httpClient = createClient(dispatch)

    const requests = tasks.map(task => {

      return httpClient.request({
        method: 'put',
        url: `${task['@id']}/cancel`,
        data: {},
        headers: {
          'Authorization': `Bearer ${jwt}`,
          'Accept': 'application/ld+json',
          'Content-Type': 'application/ld+json'
        }
      })
    })

    Promise.all(requests)
      .then(values => {
        dispatch(createTaskSuccess())
        values.forEach(response => dispatch(updateTask(response.data)))
      })
      .catch(error => dispatch(cancelTaskFailure(error)))
  }
}

export function startTasks(tasks) {

  return function(dispatch, getState) {

    const { jwt } = getState()

    dispatch(createTaskRequest())

    const httpClient = createClient(dispatch)

    const requests = tasks.map(task => {

      return httpClient.request({
        method: 'put',
        url: `${task['@id']}/start`,
        data: {},
        headers: {
          'Authorization': `Bearer ${jwt}`,
          'Accept': 'application/ld+json',
          'Content-Type': 'application/ld+json'
        }
      })
    })

    Promise.all(requests)
      .then(values => {
        dispatch(createTaskSuccess())
        values.forEach(response => dispatch(updateTask(response.data)))
      })
      .catch(error => dispatch(startTaskFailure(error)))
  }
}

export function duplicateTask(task) {

  return function(dispatch, getState) {

    const { jwt } = getState()

    dispatch(createTaskRequest())

    const url = `${task['@id']}/duplicate`

    createClient(dispatch).request({
      method: 'post',
      url,
      data: {},
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      }
    })
      .then(response => {
        dispatch(createTaskSuccess(response.data))
        dispatch(updateTask(response.data))
        dispatch(closeNewTaskModal())
      })
      .catch(error => dispatch(cancelTaskFailure(error)))
  }
}

export function restoreTasks(tasks) {

  return function(dispatch, getState) {

    const { jwt } = getState()

    dispatch(createTaskRequest())

    const httpClient = createClient(dispatch)

    const requests = tasks.map(task => {

      return httpClient.request({
        method: 'put',
        url: `${task['@id']}/restore`,
        data: {},
        headers: {
          'Authorization': `Bearer ${jwt}`,
          'Accept': 'application/ld+json',
          'Content-Type': 'application/ld+json'
        }
      })
    })

    Promise.all(requests)
      .then(values => {
        dispatch(createTaskSuccess())
        values.forEach(response => dispatch(updateTask(response.data)))
      })
      .catch(error => dispatch(restoreTaskFailure(error)))
  }
}

export function restoreTask(task) {

  return function(dispatch, getState) {

    const { jwt } = getState()

    dispatch(createTaskRequest())

    const url = `${task['@id']}/restore`

    createClient(dispatch).request({
      method: 'put',
      url,
      data: {},
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      }
    })
      .then(response => {
        dispatch(createTaskSuccess())
        dispatch(updateTask(response.data))
        dispatch(closeNewTaskModal())
      })
      .catch(error => dispatch(restoreTaskFailure(error)))
  }
}

export function rescheduleTask(task, after, before) {

  return function(dispatch, getState) {

    const { jwt } = getState()

    dispatch(createTaskRequest())

    createClient(dispatch).request({
      method: 'put',
      url: `${task['@id']}/reschedule`,
      data: { before, after },
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      }
    })
      .then(response => {
        dispatch(createTaskSuccess())
        dispatch(updateTask(response.data))
        dispatch(closeTaskRescheduleModal())
        dispatch(closeNewTaskModal())
      })
      .catch(error => dispatch(completeTaskFailure(error)))
  }
}

export function loadTaskEvents(task) {

  return function(dispatch, getState) {

    const { jwt } = getState()

    dispatch(loadTaskEventsRequest())

    const url = `${task['@id']}/events`

    createClient(dispatch).request({
      method: 'get',
      url,
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      }
    })
      .then(response => {
        dispatch(loadTaskEventsSuccess(task, response.data['hydra:member']))
      })
      .catch(error => dispatch(loadTaskEventsFailure(error)))
  }
}

export function optimizeTaskList(taskList) {

  return function(dispatch, getState) {

    const { jwt } = getState()

    const url = `${taskList['@id']}/optimize`

    createClient(dispatch).request({
      method: 'get',
      url,
      data: {},
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      }
    })
      .then(response => {
        // TODO : fix this
        dispatch(modifyTaskList(taskList.username, response.data.items))
      })
      // eslint-disable-next-line no-console
      .catch(error => console.log(error))
  }
}

export function moveTasksToNextDay(tasks) {

  return function(dispatch, getState) {

    if (tasks.length === 0) {
      return
    }

    const { jwt } = getState()

    dispatch(createTaskRequest())

    const httpClient = createClient(dispatch)

    const requests = tasks.map(task => {

      return httpClient.request({
        method: 'put',
        url: task['@id'],
        data: {
          after: moment(task.after).add(1, 'day').format(),
          before: moment(task.before).add(1, 'day').format(),
        },
        headers: {
          'Authorization': `Bearer ${jwt}`,
          'Accept': 'application/ld+json',
          'Content-Type': 'application/ld+json'
        }
      })
    })

    Promise.all(requests)
      .then(values => {
        dispatch(createTaskSuccess())
        values.forEach(response => dispatch(updateTask(response.data)))
      })
      .catch(error => dispatch(cancelTaskFailure(error)))
  }
}

export function moveTasksToNextWorkingDay(tasks) {

  return function(dispatch, getState) {

    if (tasks.length === 0) {
      return
    }

    const nextWorkingDay = selectNextWorkingDay(getState())

    const { jwt } = getState()

    dispatch(createTaskRequest())

    const httpClient = createClient(dispatch)

    const nextWorkingDayProps = {
      date:  moment(nextWorkingDay).get('date'),
      month: moment(nextWorkingDay).get('month'),
      year:  moment(nextWorkingDay).get('year'),
    }

    const requests = tasks.map(task => {

      return httpClient.request({
        method: 'put',
        url: task['@id'],
        data: {
          after: moment(task.after).set(nextWorkingDayProps).format(),
          before: moment(task.before).set(nextWorkingDayProps).format(),
        },
        headers: {
          'Authorization': `Bearer ${jwt}`,
          'Accept': 'application/ld+json',
          'Content-Type': 'application/ld+json'
        }
      })
    })

    Promise.all(requests)
      .then(values => {
        dispatch(createTaskSuccess())
        values.forEach(response => dispatch(updateTask(response.data)))
      })
      .catch(error => dispatch(cancelTaskFailure(error)))
  }
}

export function updateRightPanelSize(size) {
  return { type: size > 40 ? RIGHT_PANEL_MORE_THAN_HALF : RIGHT_PANEL_LESS_THAN_HALF }
}

export function openNewRecurrenceRuleModal() {
  return { type: OPEN_RECURRENCE_RULE_MODAL }
}

export function closeRecurrenceRuleModal() {
  return { type: CLOSE_RECURRENCE_RULE_MODAL }
}

export function setCurrentRecurrenceRule(recurrenceRule) {
  return { type: SET_CURRENT_RECURRENCE_RULE, recurrenceRule }
}

export function updateRecurrenceRuleRequest() {
  return { type: UPDATE_RECURRENCE_RULE_REQUEST }
}

export function updateRecurrenceRuleSuccess(recurrenceRule) {
  return { type: UPDATE_RECURRENCE_RULE_SUCCESS, recurrenceRule }
}

export function updateRecurrenceRuleError(message) {
  return { type: UPDATE_RECURRENCE_RULE_ERROR, message }
}

export function deleteRecurrenceRuleSuccess(recurrenceRule) {
  return { type: DELETE_RECURRENCE_RULE_SUCCESS, recurrenceRule }
}

export function saveRecurrenceRule(recurrenceRule) {

  return function(dispatch, getState) {

    const { jwt } = getState()

    const url = Object.prototype.hasOwnProperty.call(recurrenceRule, '@id') ? recurrenceRule['@id'] : '/api/recurrence_rules'
    const method = Object.prototype.hasOwnProperty.call(recurrenceRule, '@id') ? 'put' : 'post'

    const payload = _.pick(recurrenceRule, [
      'store',
      'rule',
      'template',
      'name',
    ])

    dispatch(updateRecurrenceRuleRequest())

    createClient(dispatch).request({
      method,
      url,
      data: payload,
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      }
    })
      .then(response => {
        dispatch(updateRecurrenceRuleSuccess(response.data))
        dispatch(closeRecurrenceRuleModal())
      })
      .catch(error => {
        if (error.response &&
          Object.prototype.hasOwnProperty.call(error.response.data, '@type') &&
          error.response.data['@type'] === 'ConstraintViolationList') {
          dispatch(updateRecurrenceRuleError(`${error.response.data['hydra:description']}`))
        } else {
          dispatch(updateRecurrenceRuleError('An error occurred'))
        }
      })
  }
}

export function createTasksFromRecurrenceRule(recurrenceRule) {

  return function(dispatch, getState) {

    const { jwt } = getState()
    const date = selectSelectedDate(getState())

    createClient(dispatch).request({
      method: 'post',
      url: `${recurrenceRule['@id']}/between`,
      data: {
        after: moment(date).startOf('day').format(),
        before: moment(date).endOf('day').format(),
      },
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      }
    })
      .then(() => dispatch(closeRecurrenceRuleModal()))
      // eslint-disable-next-line no-console
      .catch(error => console.log(error))
  }
}

export function deleteRecurrenceRule(recurrenceRule) {

  return function(dispatch, getState) {

    const { jwt } = getState()

    dispatch(updateRecurrenceRuleRequest())

    const resourceId = recurrenceRule['@id']

    createClient(dispatch).request({
      method: 'delete',
      url: resourceId,
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      }
    })
      .then(() => {
        dispatch(deleteRecurrenceRuleSuccess(resourceId))
        dispatch(closeRecurrenceRuleModal())
      })
      // eslint-disable-next-line no-console
      .catch(error => console.log(error))
  }
}

export function deleteGroupSuccess(group) {
  return { type: DELETE_GROUP_SUCCESS, group }
}

export function deleteGroup(group) {

  return function(dispatch, getState) {

    const { jwt } = getState()

    const resourceId = group['@id']

    createClient(dispatch).request({
      method: 'delete',
      url: resourceId,
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      }
    })
      .then(() => dispatch(deleteGroupSuccess(resourceId)))
      // eslint-disable-next-line no-console
      .catch(error => console.log(error))
  }
}

export function editGroupSuccess(group) {
  return { type: EDIT_GROUP_SUCCESS, group }
}

export function editGroup(group) {

  return function(dispatch, getState) {

    const { jwt } = getState()

    const resourceId = group['@id']

    return createClient(dispatch).request({
      method: 'put',
      url: resourceId,
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      },
      data: {
        name: group.name
      }
    })
      .then((res) => {
        dispatch(editGroupSuccess())
        return res.data
      })
      .catch(error => {
        // eslint-disable-next-line no-console
        console.log(error)
        return null
      })
  }
}

export function openExportModal() {
  return { type: OPEN_EXPORT_MODAL }
}

export function closeExportModal() {
  return { type: CLOSE_EXPORT_MODAL }
}

export function exportTasks(start, end) {

  return function(dispatch) {

    dispatch(closeExportModal())

    document.getElementById('task_export_start').value = start
    document.getElementById('task_export_end').value = end
    document.querySelector('form[name="task_export"]').submit()
  }
}

export function createGroupRequest() {
  return { type: CREATE_GROUP_REQUEST }
}

export function createGroupSuccess(taskGroup) {
  return { type: CREATE_GROUP_SUCCESS, taskGroup }
}

export function openCreateGroupModal() {
  return { type: OPEN_CREATE_GROUP_MODAL }
}

export function closeCreateGroupModal() {
  return { type: CLOSE_CREATE_GROUP_MODAL }
}

export function openReportIncidentModal() {
  return { type: OPEN_REPORT_INCIDENT_MODAL }
}

export function closeReportIncidentModal() {
  return { type: CLOSE_REPORT_INCIDENT_MODAL }
}

export function createGroup(name) {

  return function(dispatch, getState) {

    const selectedTasks = selectSelectedTasks(getState())
    const { jwt } = getState()

    dispatch(createGroupRequest())

    createClient(dispatch).request({
      method: 'post',
      url: '/api/task_groups',
      data: {
        name,
        tasks: _.map(selectedTasks, t => t['@id'])
      },
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      }
    })
      .then((response) => {
        dispatch(createGroupSuccess(response.data))
        dispatch(closeCreateGroupModal())
      })
      // eslint-disable-next-line no-console
      .catch(error => console.log(error))
  }
}

export function addTaskToGroupRequest() {
  return { type: ADD_TASK_TO_GROUP_REQUEST }
}

export function addTasksToGroupSuccess(tasks, taskGroup) {
  return { type: ADD_TASKS_TO_GROUP_SUCCESS, tasks, taskGroup }
}

export function openAddTaskToGroupModal() {
  return { type: OPEN_ADD_TASK_TO_GROUP_MODAL }
}

export function closeAddTaskToGroupModal() {
  return { type: CLOSE_ADD_TASK_TO_GROUP_MODAL }
}

export function addTasksToGroup(tasks, taskGroup) {

  return function(dispatch, getState) {

    const { jwt } = getState()

    dispatch(addTaskToGroupRequest())

    createClient(dispatch).request({
      method: 'post',
      url: `${taskGroup['@id']}/tasks`,
      data: {
        tasks: tasks.map((task) => task['@id'])
      },
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      }
    })
      .then(() => {
        dispatch(closeAddTaskToGroupModal())
        dispatch(addTasksToGroupSuccess(tasks, taskGroup))
        dispatch(clearSelectedTasks())
      })
      // eslint-disable-next-line no-console
      .catch(error => console.log(error))
  }
}

export function removeTasksFromGroupRequest() {
  return { type: REMOVE_TASK_FROM_GROUP_REQUEST }
}

export function removeTasksFromGroupSuccess(tasks) {
  return { type: REMOVE_TASKS_FROM_GROUP_SUCCESS, tasks }
}

export function removeTasksFromGroup(tasks) {

  return function(dispatch, getState) {

    const { jwt } = getState()

    dispatch(removeTasksFromGroupRequest())

    const requests = tasks.map((task) => {
      return createClient(dispatch).request({
        method: 'delete',
        url: `/api/tasks/${task.id}/group`,
        headers: {
          'Authorization': `Bearer ${jwt}`,
          'Accept': 'application/ld+json',
          'Content-Type': 'application/ld+json'
        }
      })
    })

    Promise.all(requests)
      .then(() => {
        dispatch(removeTasksFromGroupSuccess(tasks))
      })
      // eslint-disable-next-line no-console
      .catch(error => console.log(error))
  }
}

export function createDelivery(tasks, store) {

  return function(dispatch, getState) {

    const { jwt } = getState()

    createClient(dispatch).request({
      method: 'post',
      url: '/api/deliveries/from_tasks',
      data: {
        tasks: _.map(tasks, t => t['@id']),
        store: store['@id'],
      },
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      }
    })
      // eslint-disable-next-line no-unused-vars
      .then((response) => {

        const pickup = _.find(tasks, t => t.type === 'PICKUP')
        const dropoffs = _.without(tasks, pickup)
        const dropoffsWithPrevious = _.map(dropoffs, t => {
          return {
            ...t,
            previous: pickup['@id'],
          }
        })

        dropoffsWithPrevious.forEach(t => dispatch(updateTask(t)))

        dispatch(closeCreateDeliveryModal())

      })
      .catch(error => {
        // eslint-disable-next-line no-console
        console.log(error)
        dispatch(closeCreateDeliveryModal())
      })
  }
}

export function openCreateDeliveryModal() {
  return { type: OPEN_CREATE_DELIVERY_MODAL }
}

export function closeCreateDeliveryModal() {
  return { type: CLOSE_CREATE_DELIVERY_MODAL }
}

export function openCreateTourModal() {
  return { type: OPEN_CREATE_TOUR_MODAL }
}

export function closeCreateTourModal() {
  return { type: CLOSE_CREATE_TOUR_MODAL }
}

export function openTaskRescheduleModal() {
  return { type: OPEN_TASK_RESCHEDULE_MODAL }
}

export function closeTaskRescheduleModal() {
  return { type: CLOSE_TASK_RESCHEDULE_MODAL }
}

export function createTourRequest() {
  return { type: CREATE_TOUR_REQUEST }
}

export function createTourRequestSuccess() {
  return { type: CREATE_TOUR_REQUEST_SUCCESS }
}

/**
 * @param {Object} tour - tour that will be modified
 * @param {Array.string} items - list of tasks IRIs
 */
export function modifyTourRequest(tour, items) {
  return { type: MODIFY_TOUR_REQUEST, tour, items }
}

export function modifyTourRequestSuccess(tour) {
  return { type: MODIFY_TOUR_REQUEST_SUCCESS, tour }
}

export function modifyTourRequestError(tour, tasks) {
  return { type: MODIFY_TOUR_REQUEST_ERROR, tour, tasks }
}


export function toggleTourLoading(tourId) {
  /*
    Block/unblock actions on tour while we are modifying it.
  */
  return { type: TOGGLE_TOUR_LOADING, tourId }
}

export function createTour(tasks, name, date) {
  return function(dispatch, getState) {

    const { jwt } = getState()

    dispatch(createTourRequest())


    createClient(dispatch).request({
      method: 'post',
      url: '/api/tours',
      data: {
        name,
        tasks: _.map(tasks, t => t['@id']),
        date: date.format('YYYY-MM-DD'),
      },
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      }
    })
      .then((response) => {
        let tour = {...response.data}

        dispatch(updateTour(tour))
        dispatch(createTourRequestSuccess())
        dispatch(toggleTourPanelExpanded(tour['@id']))
        dispatch(closeCreateTourModal())
      })
      .catch(error => {
        // eslint-disable-next-line no-console
        console.log(error)
        dispatch(closeCreateTourModal())
      })
  }
}

export function updateTourInUI(tour, tasks) {
  return async function(dispatch) {
    dispatch(toggleTourLoading(tour['@id']))
    dispatch(modifyTourRequest(tour, tasks))
  }
}

/**
 * @param {Object} tour - tour that will be modified
 * @param {Array.string} tasks - list of tasks IRIs
 */
export function modifyTour(tour, tasks) {

  return async function(dispatch, getState) {

    const { jwt } = getState()

    tasks = _.map(tasks, t => t['@id'] || t)

    dispatch(updateTourInUI(tour, tasks))

    let response

    try {
      response = await createClient(dispatch).request({
        method: 'put',
        url: tour['@id'],
        data: {
          name: tour.name,
          tasks: tasks
        },
        headers: {
          'Authorization': `Bearer ${jwt}`,
          'Accept': 'application/ld+json',
          'Content-Type': 'application/ld+json'
        }
      })
    } catch (error) {
        // eslint-disable-next-line no-console
        console.error(error)
        dispatch(modifyTourRequestError(tour))
    }

    let _tour = response.data
    dispatch(modifyTourRequestSuccess(_tour))
    dispatch(toggleTourLoading(tour['@id']))

    return _tour
  }
}

export function deleteTourSuccess(tour) {
  return { type: DELETE_TOUR_SUCCESS, tour }
}

export function deleteTour(tour) {

  return function(dispatch, getState) {

    const { jwt } = getState()

    let resourceId = tour['@id'];

    dispatch(toggleTourLoading(resourceId))

    createClient(dispatch).request({
      method: 'delete',
      url: resourceId,
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      }
    })
      .then(() => {
        dispatch(deleteTourSuccess(resourceId))
        dispatch(toggleTourLoading(resourceId))
      })
      // eslint-disable-next-line no-console
      .catch(error => console.log(error))
  }
}

/**
 * @param {Object} tour - tour that will be modified
 * @param {Array.Object} tasks - list of tasks objects
 */
export function removeTasksFromTour(tour, tasks, modifyTourAction=modifyTour) {

  if (!Array.isArray(tasks)) {
    tasks = [ tasks ]
  }

  return function(dispatch) {
    let newTourItems = withoutItemsIRIs(tour.items, tasks.map(t => t['@id']))
    dispatch(modifyTourAction(tour, newTourItems))
  }
}

export function setToursEnabled(enabled) {
  return {type: SET_TOURS_ENABLED, enabled}
}

export function onlyFilter(filter) {
  return function(dispatch) {
    ['showFinishedTasks', 'showCancelledTasks', 'showIncidentReportedTasks'].forEach(key => {
      dispatch(setFilterValue(key, key == filter))
    })

    dispatch(setFilterValue('onlyFilter', filter))
    dispatch(closeFiltersModal())

  }
}


export function loadOrganizations() {

  return async function(dispatch, getState) {

    const { jwt } = getState()
    const client = createClient(dispatch)

    const data = await client.paginatedRequest({
      method: 'GET',
      url: window.Routing.generate('api_organizations_get_collection'),
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      }
    })
    dispatch(loadOrganizationsSuccess(data))
  }
}