import _ from 'lodash'
import axios from 'axios'
import moment from 'moment'

import { taskComparator, withoutTasks, withLinkedTasks, isInDateRange } from './utils'
import {
  selectSelectedDate,
  selectTaskLists,
  selectAllTasks,
  createTaskListRequest,
  createTaskListSuccess,
  createTaskListFailure,
} from '../../coopcycle-frontend-js/logistics/redux'
import { selectNextWorkingDay, selectSelectedTasks } from './selectors'

function createClient(dispatch) {

  const client = axios.create({
    baseURL: location.protocol + '//' + location.hostname
  })

  let subscribers = []
  let isRefreshingToken = false

  function onTokenFetched(token) {
    subscribers.forEach(callback => callback(token))
    subscribers = []
  }

  function addSubscriber(callback) {
    subscribers.push(callback)
  }

  function refreshToken() {
    return new Promise((resolve) => {
      // TODO Check response is OK, reject promise
      $.getJSON(window.Routing.generate('profile_jwt')).then(result => resolve(result.jwt))
    })
  }

  // @see https://gist.github.com/Godofbrowser/bf118322301af3fc334437c683887c5f
  // @see https://www.techynovice.com/setting-up-JWT-token-refresh-mechanism-with-axios/
  client.interceptors.response.use(
    response => response,
    error => {

      if (error.response && error.response.status === 401) {

        try {

          const req = error.config

          const retry = new Promise(resolve => {
            addSubscriber(token => {
              req.headers['Authorization'] = `Bearer ${token}`
              resolve(axios(req))
            })
          })

          if (!isRefreshingToken) {

            isRefreshingToken = true

            refreshToken()
              .then(token => {
                dispatch(tokenRefreshSuccess(token))
                return token
              })
              .then(token => onTokenFetched(token))
              .catch(error => Promise.reject(error))
              .finally(() => {
                isRefreshingToken = false
              })
          }

          return retry
        } catch (e) {
          return Promise.reject(e)
        }
      }

      return Promise.reject(error)
    }
  )

  return client
}

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
export const SELECT_TASKS_BY_IDS = 'SELECT_TASKS_BY_IDS'
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
export const TOKEN_REFRESH_SUCCESS = 'TOKEN_REFRESH_SUCCESS'

export const OPEN_FILTERS_MODAL = 'OPEN_FILTERS_MODAL'
export const CLOSE_FILTERS_MODAL = 'CLOSE_FILTERS_MODAL'
export const SET_FILTER_VALUE = 'SET_FILTER_VALUE'
export const RESET_FILTERS = 'RESET_FILTERS'

export const TOGGLE_SEARCH = 'TOGGLE_SEARCH'
export const OPEN_SEARCH = 'OPEN_SEARCH'
export const CLOSE_SEARCH = 'CLOSE_SEARCH'

export const OPEN_SETTINGS = 'OPEN_SETTINGS'
export const CLOSE_SETTINGS = 'CLOSE_SETTINGS'
export const SET_POLYLINE_STYLE = 'SET_POLYLINE_STYLE'
export const SET_CLUSTERS_ENABLED = 'SET_CLUSTERS_ENABLED'

export const LOAD_TASK_EVENTS_REQUEST = 'LOAD_TASK_EVENTS_REQUEST'
export const LOAD_TASK_EVENTS_SUCCESS = 'LOAD_TASK_EVENTS_SUCCESS'
export const LOAD_TASK_EVENTS_FAILURE = 'LOAD_TASK_EVENTS_FAILURE'

export const SET_TASK_LISTS_LOADING = 'SET_TASK_LISTS_LOADING'

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

export const DELETE_GROUP_SUCCESS = 'DELETE_GROUP_SUCCESS'

export function setTaskListsLoading(loading = true) {
  return { type: SET_TASK_LISTS_LOADING, loading }
}

export function assignAfter(username, task, after) {

  return function(dispatch, getState) {

    let state = getState()
    let allTasks = selectAllTasks(state)
    let taskLists = selectTaskLists(state)

    const taskList = _.find(taskLists, taskList => taskList.username === username)
    const taskIndex = _.findIndex(taskList.items, t => taskComparator(t, after))

    if (-1 !== taskIndex) {
      const newTaskListItems = taskList.items.slice()
      Array.prototype.splice.apply(newTaskListItems,
        Array.prototype.concat([ taskIndex + 1, 0 ], withLinkedTasks(task, allTasks))
      )
      dispatch(modifyTaskList(username, newTaskListItems))
    }
  }
}

export function unassignTasks(username, tasks) {

  if (!Array.isArray(tasks)) {
    tasks = [ tasks ]
  }

  return function(dispatch, getState) {

    if (tasks.length === 0) {
      return
    }

    let state = getState()
    let allTasks = selectAllTasks(state)
    let taskLists = selectTaskLists(state)

    const taskList = _.find(taskLists, taskList => taskList.username === username)

    dispatch(modifyTaskList(username, withoutTasks(taskList.items, withLinkedTasks(tasks, allTasks))))
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

export function modifyTaskListRequest(username, tasks) {
  return { type: MODIFY_TASK_LIST_REQUEST, username, tasks }
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

export function modifyTaskList(username, tasks) {

  const data = tasks.map((task, index) => ({
    task: task['@id'],
    position: index,
  }))

  return function(dispatch, getState) {

    let state = getState()
    let allTasks = selectAllTasks(state)
    let date = selectSelectedDate(state)

    const url = window.Routing.generate('admin_task_list_modify', {
      date: date.format('YYYY-MM-DD'),
      username,
    })

    const newTasks = tasks.map((task, position) => {
      const rt = _.find(allTasks, t => t['@id'] === task['@id'])

      return {
        ...rt,
        position,
      }
    })

    dispatch(modifyTaskListRequest(username, newTasks))

    axios
      .put(url, data, {
        withCredentials: true,
        headers: {
          'Content-Type': 'application/ld+json'
        },
      })
      .then(res => dispatch(modifyTaskListRequestSuccess(res.data)))
      .catch(error => {
        // eslint-disable-next-line no-console
        console.error(error)
      })
  }
}

export function togglePolyline(username) {
  return { type: TOGGLE_POLYLINE, username }
}

export function taskListsUpdated(taskLists) {
  return { type: TASK_LISTS_UPDATED, taskLists }
}

export function toggleTask(task, multiple = false) {
  return { type: TOGGLE_TASK, task, multiple }
}

export function selectTask(task) {
  return { type: SELECT_TASK, task }
}

export function selectTasks(tasks) {
  return { type: SELECT_TASKS, tasks }
}

export function selectTasksByIds(taskIds) {
  return { type: SELECT_TASKS_BY_IDS, taskIds }
}

export function clearSelectedTasks() {
  return { type: CLEAR_SELECTED_TASKS }
}

export function setTaskListGroupMode(mode) {
  return { type: SET_TASK_LIST_GROUP_MODE, mode }
}

export function createTaskList(date, username) {

  return function(dispatch) {

    const url = window.Routing.generate('admin_task_list_create', {
      date: date.format('YYYY-MM-DD'),
      username
    })

    dispatch(createTaskListRequest())

    return axios.post(url, {}, {
      withCredentials: true,
      headers: {
        'Content-Type': 'application/json'
      },
    })
      .then(res => dispatch(createTaskListSuccess(res.data)))
      .catch(error => dispatch(createTaskListFailure(error)))
  }
}

function moveTo(task, direction) {

  return function(dispatch, getState) {

    const taskLists = selectTaskLists(getState())
    const taskList = _.find(taskLists, taskList => taskList.username === task.assignedTo)

    if (taskList) {
      const newTasks = taskList.items.filter(item => item['@id'] !== task['@id'])
      switch (direction) {
        case 'top':
          newTasks.unshift(task)
          break
        case 'bottom':
          newTasks.push(task)
          break
      }
      dispatch(modifyTaskList(taskList.username, newTasks))
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

export function completeTaskFailure(error) {
  return { type: COMPLETE_TASK_FAILURE, error }
}

export function cancelTaskFailure(error) {
  return { type: CANCEL_TASK_FAILURE, error }
}

export function tokenRefreshSuccess(token) {
  return { type: TOKEN_REFRESH_SUCCESS, token }
}

export function openFiltersModal() {
  return { type: OPEN_FILTERS_MODAL }
}

export function closeFiltersModal() {
  return { type: CLOSE_FILTERS_MODAL }
}

export function toggleSearch() {
  return { type: TOGGLE_SEARCH }
}

export function openSearch() {
  return { type: OPEN_SEARCH }
}

export function closeSearch() {
  return { type: CLOSE_SEARCH }
}

export function openSettings() {
  return { type: OPEN_SETTINGS }
}

export function closeSettings() {
  return { type: CLOSE_SETTINGS }
}

export function setPolylineStyle(style) {
  return {type: SET_POLYLINE_STYLE, style}
}

export function setClustersEnabled(enabled) {
  return {type: SET_CLUSTERS_ENABLED, enabled}
}

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

export function handleDragStart(result) {

  return function(dispatch, getState) {

    const selectedTasks = getState().selectedTasks

    // If the user is starting to drag something that is not selected then we need to clear the selection.
    // https://github.com/atlassian/react-beautiful-dnd/blob/master/docs/patterns/multi-drag.md#dragging
    const isDraggableSelected = selectedTasks.includes(result.draggableId)

    if (!isDraggableSelected) {
      dispatch(clearSelectedTasks())
    }
  }
}

export function handleDragEnd(result) {

  return function(dispatch, getState) {

    // dropped nowhere
    if (!result.destination) {
      return;
    }

    const source = result.source;
    const destination = result.destination;

    // reodered inside the unassigned list, do nothing
    if (
      source.droppableId === destination.droppableId &&
      source.droppableId === 'unassigned'
    ) {
      return;
    }

    // did not move anywhere - can bail early
    if (
      source.droppableId === destination.droppableId &&
      source.index === destination.index
    ) {
      return;
    }

    // cannot unassign by drag'n'drop atm
    if (source.droppableId.startsWith('assigned:') && destination.droppableId === 'unassigned') {
      return
    }

    const allTasks = selectAllTasks(getState())
    const taskLists = selectTaskLists(getState())
    const selectedTasks = selectSelectedTasks(getState())

    const username = destination.droppableId.replace('assigned:', '')
    const taskList = _.find(taskLists, tl => tl.username === username)
    const newTasks = [ ...taskList.items ]

    if (selectedTasks.length > 1) {

      // FIXME Manage linked tasks
      // FIXME
      // The tasks are dropped in the order they were selected
      // Instead, we should respect the order of the unassigned tasks

      Array.prototype.splice.apply(newTasks,
        Array.prototype.concat([ result.destination.index, 0 ], selectedTasks))

    } else if (result.draggableId.startsWith('group:')) {

      const groupEl = document.querySelector(`[data-rbd-draggable-id="${result.draggableId}"]`)

      const tasksFromGroup = Array
        .from(groupEl.querySelectorAll('[data-task-id]'))
        .map(el => _.find(allTasks, t => t['@id'] === el.getAttribute('data-task-id')))

      Array.prototype.splice.apply(newTasks,
        Array.prototype.concat([ result.destination.index, 0 ], tasksFromGroup))

    } else {

      // Reorder inside same list
      if (source.droppableId === destination.droppableId) {
        const [ removed ] = newTasks.splice(result.source.index, 1);
        newTasks.splice(result.destination.index, 0, removed)
      } else {

        const task = _.find(allTasks, t => t['@id'] === result.draggableId)

        newTasks.splice(result.destination.index, 0, task)

        if (task && task.previous) {
          // If previous task is another day, will be null
          const previousTask = _.find(allTasks, t => t['@id'] === task.previous)
          if (previousTask) {
            Array.prototype.splice.apply(newTasks,
              Array.prototype.concat([ result.destination.index, 0 ], previousTask))
          }
        } else if (task && task.next) {
          // If next task is another day, will be null
          const nextTask = _.find(allTasks, t => t['@id'] === task.next)
          if (nextTask) {
            Array.prototype.splice.apply(newTasks,
              Array.prototype.concat([ result.destination.index + 1, 0 ], nextTask))
          }
        }

      }

    }

    dispatch(modifyTaskList(username, newTasks))
  }
}
