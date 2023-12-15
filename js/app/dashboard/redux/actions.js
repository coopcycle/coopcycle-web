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
  makeSelectTaskListItemsByUsername,
  enableUnassignedTourTasksDroppable,
  disableUnassignedTourTasksDroppable,
} from '../../coopcycle-frontend-js/logistics/redux'
import { selectNextWorkingDay, selectSelectedTasks } from './selectors'
import { selectUnassignedTours } from '../../../shared/src/logistics/redux/selectors'

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
export const RESTORE_TASK_FAILURE = 'RESTORE_TASK_FAILURE'

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
export const SET_USE_AVATAR_COLORS = 'SET_USE_AVATAR_COLORS'

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
export const SHOW_RECURRENCE_RULES = 'SHOW_RECURRENCE_RULES'

export const DELETE_GROUP_SUCCESS = 'DELETE_GROUP_SUCCESS'
export const EDIT_GROUP_SUCCESS = 'EDIT_GROUP_SUCCESS'

export const OPEN_CREATE_GROUP_MODAL = 'OPEN_CREATE_GROUP_MODAL'
export const CLOSE_CREATE_GROUP_MODAL = 'CLOSE_CREATE_GROUP_MODAL'

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
export const UPDATE_TOUR = 'UPDATE_TOUR'
export const DELETE_TOUR_SUCCESS = 'DELETE_TOUR_SUCCESS'

export const SET_TOURS_ENABLED = 'SET_TOURS_ENABLED'

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
      .then(res => {
        dispatch(modifyTaskListRequestSuccess(res.data))
      })
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

export function restoreTaskFailure(error) {
  return { type: RESTORE_TASK_FAILURE, error }
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

export function setUseAvatarColors(useAvatarColors) {
  return {type: SET_USE_AVATAR_COLORS, useAvatarColors}
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

export function handleDragStart(result) {
  return function(dispatch, getState) {

    const selectedTasks = getState().selectedTasks

    // If the user is starting to drag something that is not selected then we need to clear the selection.
    // https://github.com/atlassian/react-beautiful-dnd/blob/master/docs/patterns/multi-drag.md#dragging
    const isDraggableSelected = selectedTasks.includes(result.draggableId)

    if (!isDraggableSelected) {
      dispatch(clearSelectedTasks())
    }

    if (result.source.droppableId == 'unassigned_tours') {
      dispatch(disableUnassignedTourTasksDroppable())
    }
  }
}

export function handleDragEnd(result) {

  return function(dispatch, getState) {

    dispatch(enableUnassignedTourTasksDroppable())

    // dropped nowhere
    if (!result.destination) {
      return;
    }

    const source = result.source;
    const destination = result.destination;

    // reordered inside the unassigned list or unassigned tours list, do nothing
    if (
      source.droppableId === destination.droppableId &&
      ( source.droppableId === 'unassigned' || source.droppableId === 'unassigned_tours' )
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

    // cannot unassign from tour by drag'n'drop atm
    if (source.droppableId.startsWith('unassigned_tour:') && destination.droppableId === 'unassigned') {
      return
    }

    const allTasks = selectAllTasks(getState())

    if (destination.droppableId.startsWith('unassigned_tour:')) {

      const tours = selectUnassignedTours(getState())
      const tourId = destination.droppableId.replace('unassigned_tour:', '')
      const tour = tours.find(t => t['@id'] == tourId)

      const newTourItems = [ ...tour.items ]

      // Drop new task into existing tour
      if (source.droppableId === 'unassigned') {
        const task = _.find(allTasks, t => t['@id'] === result.draggableId)
        newTourItems.splice(result.destination.index, 0, task)
      }

      // Reorder tasks of existing tour
      if (source.droppableId === destination.droppableId) {
        const [ removed ] = newTourItems.splice(result.source.index, 1);
        newTourItems.splice(result.destination.index, 0, removed)
      }

      dispatch(modifyTour(tour, newTourItems))

      return
    }

    const taskLists = selectTaskLists(getState())
    const selectedTasks = selectSelectedTasks(getState())

    const username = destination.droppableId.replace('assigned:', '')
    const taskList = _.find(taskLists, tl => tl.username === username)
    const newTasks = [ ...taskList.items ]

    const selectTaskListItemsByUsername = makeSelectTaskListItemsByUsername()
    const taskListItemsByUsername = selectTaskListItemsByUsername(getState(), { username })

    if (selectedTasks.length > 1) {

      // FIXME Manage linked tasks
      // FIXME
      // The tasks are dropped in the order they were selected
      // Instead, we should respect the order of the unassigned tasks

      Array.prototype.splice.apply(newTasks,
        Array.prototype.concat([ result.destination.index, 0 ], selectedTasks))

    } else if (result.draggableId.startsWith('group:') || result.draggableId.startsWith('tour:')) {

      const groupEl = document.querySelector(`[data-rbd-draggable-id="${result.draggableId}"]`)

      const tasksFromGroup = Array
        .from(groupEl.querySelectorAll('[data-task-id]'))
        .map(el => _.find(allTasks, t => t['@id'] === el.getAttribute('data-task-id')))

      Array.prototype.splice.apply(newTasks,
        Array.prototype.concat([ result.destination.index, 0 ], tasksFromGroup))

    } else {

      // Reorder inside same list
      if (source.droppableId === destination.droppableId) {
        const [ removed ] = taskListItemsByUsername.splice(result.source.index, 1);
        const newTaskListItemsByUsername = [ ...taskListItemsByUsername ]
        newTaskListItemsByUsername.splice(result.destination.index, 0, removed)

        // Flatten list
        const flatArray = newTaskListItemsByUsername.reduce((items, item) => {
          if (item['@type'] === 'Tour') {
            item.items.forEach(t => items.push(t))
          } else {
            items.push(item)
          }
          return items
        }, [])

        newTasks.length = 0; // Clear the array
        newTasks.push(...flatArray);

      } else {
        const task = _.find(allTasks, t => t['@id'] === result.draggableId)
        if (task) {
          const linkedTasks = withLinkedTasks(task, allTasks)
          Array.prototype.splice.apply(newTasks,
            Array.prototype.concat([ result.destination.index, 0 ], linkedTasks))
        }
      }

    }

    dispatch(modifyTaskList(username, newTasks))
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

export function modifyTourRequest(tour, tasks) {
  return { type: MODIFY_TOUR_REQUEST, tour, tasks }
}

export function modifyTourRequestSuccess(tour, tasks) {
  return { type: MODIFY_TOUR_REQUEST_SUCCESS, tour, tasks }
}

export function modifyTourRequestError(tour, tasks) {
  return { type: MODIFY_TOUR_REQUEST_ERROR, tour, tasks }
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
        tasks.forEach(task => dispatch(updateTask({ ...task, tour: response.data })))
        // flatten items to itmIds
        let tour = {...response.data}
        tour.itemIds = tour.items.map(item => item['@id'])

        dispatch(updateTour(tour))
        dispatch(createTourRequestSuccess())
        dispatch(closeCreateTourModal())
      })
      .catch(error => {
        // eslint-disable-next-line no-console
        console.log(error)
        dispatch(closeCreateTourModal())
      })
  }
}

export function modifyTour(tour, tasks) {

  return function(dispatch, getState) {

    dispatch(modifyTourRequest(tour, tasks))

    const { jwt } = getState()

    createClient(dispatch).request({
      method: 'put',
      url: tour['@id'],
      data: {
        name: tour.name,
        tasks: _.map(tasks, t => t['@id'])
      },
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      }
    })
      .then(res => {
        let _tour = res.data
        // TODO: do this in the backend?
        _tour.itemIds = _tour.items.map(item => item['@id'])
        
        dispatch(updateTour(_tour))
        dispatch(modifyTourRequestSuccess(_tour, tasks))
        return _tour
      })
      .catch(error => {
        // eslint-disable-next-line no-console
        console.error(error)
        dispatch(modifyTourRequestError(tour))
      })
  }
}

export function deleteTourSuccess(tour) {
  return { type: DELETE_TOUR_SUCCESS, tour }
}

export function deleteTour(tour) {

  return function(dispatch, getState) {

    const { jwt } = getState()

    let resourceId = tour['@id'];

    createClient(dispatch).request({
      method: 'delete',
      url: resourceId,
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      }
    })
      .then(() => dispatch(deleteTourSuccess(resourceId)))
      // eslint-disable-next-line no-console
      .catch(error => console.log(error))
  }
}

export function removeTaskFromTour(tour, task) {

  return function(dispatch) {
    dispatch(modifyTour(tour, withoutTasks(tour.items, [ task ])))
  }
}

export function setToursEnabled(enabled) {
  return {type: SET_TOURS_ENABLED, enabled}
}
