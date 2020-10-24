import _ from 'lodash'
import axios from 'axios'
import { taskComparator, withoutTasks, withLinkedTasks } from './utils'
import {
  selectSelectedDate,
  selectTaskLists,
  selectAllTasks,
  createTaskListRequest,
  createTaskListSuccess,
  createTaskListFailure,
} from '../../coopcycle-frontend-js/logistics/redux'
import {moment} from "../../coopcycle-frontend-js";

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
export const TASK_LIST_UPDATED = 'TASK_LIST_UPDATED'
export const TOGGLE_POLYLINE = 'TOGGLE_POLYLINE'
export const TOGGLE_TASK = 'TOGGLE_TASK'
export const SELECT_TASK = 'SELECT_TASK'
export const SELECT_TASKS = 'SELECT_TASKS'
export const CLEAR_SELECTED_TASKS = 'CLEAR_SELECTED_TASKS'
export const SET_TASK_LIST_GROUP_MODE = 'SET_TASK_LIST_GROUP_MODE'

export const SET_GEOLOCATION = 'SET_GEOLOCATION'
export const SET_OFFLINE = 'SET_OFFLINE'
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

function setTaskListsLoading(loading = true) {
  return { type: SET_TASK_LISTS_LOADING, loading }
}

function assignAfter(username, task, after) {

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

function removeTasks(username, tasks) {

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

function _updateTask(task) {
  return {type: UPDATE_TASK, task}
}

function openAddUserModal() {
  return {type: OPEN_ADD_USER}
}

function closeAddUserModal() {
  return {type: CLOSE_ADD_USER}
}

function modifyTaskListRequest(username, tasks) {
  return { type: MODIFY_TASK_LIST_REQUEST, username, tasks }
}

function modifyTaskListRequestSuccess(taskList) {
  return { type: MODIFY_TASK_LIST_REQUEST_SUCCESS, taskList }
}

function setFilterValue(key, value) {
  return { type: SET_FILTER_VALUE, key, value }
}

function resetFilters() {
  return { type: RESET_FILTERS }
}

function addImport(token) {
  return { type: ADD_IMPORT, token }
}

function importSuccess(token) {
  return { type: IMPORT_SUCCESS, token }
}

function importError(token, message) {
  return { type: IMPORT_ERROR, token, message }
}

function modifyTaskList(username, tasks) {

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

function togglePolyline(username) {
  return { type: TOGGLE_POLYLINE, username }
}

function taskListUpdated(taskList) {
  return { type: TASK_LIST_UPDATED, taskList }
}

function toggleTask(task, multiple = false) {
  return { type: TOGGLE_TASK, task, multiple }
}

function selectTask(task) {
  return { type: SELECT_TASK, task }
}

function selectTasks(tasks) {
  return { type: SELECT_TASKS, tasks }
}

function clearSelectedTasks() {
  return { type: CLEAR_SELECTED_TASKS }
}

function setTaskListGroupMode(mode) {
  return { type: SET_TASK_LIST_GROUP_MODE, mode }
}

function createTaskList(date, username) {

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

function moveToTop(task) {

  return function(dispatch, getState) {

    let state = getState()
    let taskLists = selectTaskLists(state)

    const taskList = _.find(taskLists, taskList => taskList.username === task.assignedTo)

    if (taskList) {
      const newTasks = taskList.items.filter(item => item['@id'] !== task['@id'])
      newTasks.unshift(task)
      dispatch(modifyTaskList(taskList.username, newTasks))
    }
  }
}

function moveToBottom(task) {

  return function(dispatch, getState) {

    let state = getState()
    let taskLists = selectTaskLists(state)

    const taskList = _.find(taskLists, taskList => taskList.username === task.assignedTo)

    if (taskList) {
      const newTasks = taskList.items.filter(item => item['@id'] !== task['@id'])
      newTasks.push(task)
      dispatch(modifyTaskList(taskList.username, newTasks))
    }
  }
}

function setGeolocation(username, coords, timestamp) {
  return { type: SET_GEOLOCATION, username, coords, timestamp }
}

function setOffline(username) {
  return { type: SET_OFFLINE, username }
}

function openNewTaskModal() {
  return { type: OPEN_NEW_TASK_MODAL }
}

function closeNewTaskModal() {
  return { type: CLOSE_NEW_TASK_MODAL }
}

function setCurrentTask(task) {
  return { type: SET_CURRENT_TASK, task }
}

function createTaskRequest() {
  return { type: CREATE_TASK_REQUEST }
}

function createTaskSuccess(task) {
  return { type: CREATE_TASK_SUCCESS, task }
}

function createTaskFailure(error) {
  return { type: CREATE_TASK_FAILURE, error }
}

function completeTaskFailure(error) {
  return { type: COMPLETE_TASK_FAILURE, error }
}

function cancelTaskFailure(error) {
  return { type: CANCEL_TASK_FAILURE, error }
}

function tokenRefreshSuccess(token) {
  return { type: TOKEN_REFRESH_SUCCESS, token }
}

function openFiltersModal() {
  return { type: OPEN_FILTERS_MODAL }
}

function closeFiltersModal() {
  return { type: CLOSE_FILTERS_MODAL }
}

function toggleSearch() {
  return { type: TOGGLE_SEARCH }
}

function openSearch() {
  return { type: OPEN_SEARCH }
}

function closeSearch() {
  return { type: CLOSE_SEARCH }
}

function openSettings() {
  return { type: OPEN_SETTINGS }
}

function closeSettings() {
  return { type: CLOSE_SETTINGS }
}

function setPolylineStyle(style) {
  return {type: SET_POLYLINE_STYLE, style}
}

function setClustersEnabled(enabled) {
  return {type: SET_CLUSTERS_ENABLED, enabled}
}

function loadTaskEventsRequest() {
  return { type: LOAD_TASK_EVENTS_REQUEST }
}

function loadTaskEventsSuccess(task, events) {
  return { type: LOAD_TASK_EVENTS_SUCCESS, task, events }
}

function loadTaskEventsFailure(error) {
  return { type: LOAD_TASK_EVENTS_FAILURE, error }
}

function openImportModal() {
  return { type: OPEN_IMPORT_MODAL }
}

function closeImportModal() {
  return { type: CLOSE_IMPORT_MODAL }
}

const acceptTask = (task, date) => {

  const dateAsRange = moment.range(
    moment(date).set({ hour:  0, minute:  0, second:  0 }),
    moment(date).set({ hour: 23, minute: 59, second: 59 })
  )

  const range = moment.range(
    moment(task.doneAfter),
    moment(task.doneBefore)
  )

  return range.overlaps(dateAsRange)
}

function updateTask(dispatch, getState, task) {
  let date = selectSelectedDate(getState)

  if (acceptTask(task, date)) {
    dispatch(_updateTask(task))
  }
}

function createTask(task) {

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
        updateTask(dispatch, getState, response.data)
        dispatch(closeNewTaskModal())
      })
      .catch(error => dispatch(createTaskFailure(error)))
  }
}

function startTask(task) {

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
        updateTask(dispatch, getState, response.data)
        dispatch(closeNewTaskModal())
      })
      .catch(error => dispatch(completeTaskFailure(error)))
  }
}

function completeTask(task, notes = '', success = true) {

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
        updateTask(dispatch, getState, response.data)
        dispatch(closeNewTaskModal())
      })
      .catch(error => dispatch(completeTaskFailure(error)))
  }
}

function cancelTask(task) {

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
        updateTask(dispatch, getState, response.data)
        dispatch(closeNewTaskModal())
      })
      .catch(error => dispatch(cancelTaskFailure(error)))
  }
}

function cancelTasks(tasks) {

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
        values.forEach(response => updateTask(dispatch, getState, response.data))
      })
      .catch(error => dispatch(cancelTaskFailure(error)))
  }
}

function duplicateTask(task) {

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
        updateTask(dispatch, getState, response.data)
        dispatch(closeNewTaskModal())
      })
      .catch(error => dispatch(cancelTaskFailure(error)))
  }
}

function loadTaskEvents(task) {

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

export {
  assignAfter,
  updateTask,
  createTaskList,
  modifyTaskList,
  removeTasks,
  openAddUserModal,
  closeAddUserModal,
  togglePolyline,
  setTaskListGroupMode,
  toggleTask,
  selectTask,
  selectTasks,
  setGeolocation,
  setOffline,
  openNewTaskModal,
  closeNewTaskModal,
  setCurrentTask,
  createTask,
  completeTask,
  cancelTask,
  duplicateTask,
  openFiltersModal,
  closeFiltersModal,
  setFilterValue,
  resetFilters,
  toggleSearch,
  openSearch,
  closeSearch,
  openSettings,
  closeSettings,
  setPolylineStyle,
  cancelTasks,
  loadTaskEvents,
  setTaskListsLoading,
  moveToTop,
  moveToBottom,
  openImportModal,
  closeImportModal,
  addImport,
  importSuccess,
  importError,
  startTask,
  setClustersEnabled,
  taskListUpdated,
  clearSelectedTasks,
}
