import _ from 'lodash'
import axios from 'axios'

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
      $.getJSON(window.Routing.generate('profile_jwt')).then(token => resolve(token))
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
              .catch(e => Promise.reject(e))
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

export const ASSIGN_TASKS = 'ASSIGN_TASKS'
export const ADD_CREATED_TASK = 'ADD_CREATED_TASK'
export const REMOVE_TASKS = 'REMOVE_TASKS'
export const UPDATE_TASK = 'UPDATE_TASK'
export const OPEN_ADD_USER = 'OPEN_ADD_USER'
export const CLOSE_ADD_USER = 'CLOSE_ADD_USER'
export const MODIFY_TASK_LIST_REQUEST = 'MODIFY_TASK_LIST_REQUEST'
export const MODIFY_TASK_LIST_REQUEST_SUCCESS = 'MODIFY_TASK_LIST_REQUEST_SUCCESS'
export const TOGGLE_POLYLINE = 'TOGGLE_POLYLINE'
export const TOGGLE_TASK = 'TOGGLE_TASK'
export const SELECT_TASK = 'SELECT_TASK'
export const SET_TASK_LIST_GROUP_MODE = 'SET_TASK_LIST_GROUP_MODE'
export const ADD_TASK_LIST_REQUEST = 'ADD_TASK_LIST_REQUEST'
export const ADD_TASK_LIST_REQUEST_SUCCESS = 'ADD_TASK_LIST_REQUEST_SUCCESS'
export const SET_GEOLOCATION = 'SET_GEOLOCATION'
export const SET_OFFLINE = 'SET_OFFLINE'
export const DRAKE_DRAG = 'DRAKE_DRAG'
export const DRAKE_DRAGEND = 'DRAKE_DRAGEND'
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

export const LOAD_TASK_EVENTS_REQUEST = 'LOAD_TASK_EVENTS_REQUEST'
export const LOAD_TASK_EVENTS_SUCCESS = 'LOAD_TASK_EVENTS_SUCCESS'
export const LOAD_TASK_EVENTS_FAILURE = 'LOAD_TASK_EVENTS_FAILURE'

function assignTasks(username, tasks) {

  return function(dispatch, getState) {

    const { allTasks } = getState()

    const newTasks = []
    tasks.forEach(task => {
      // FIXME
      // Make it work when more than 2 tasks are linked together
      if (task.previous) {
        // If previous task is another day, will be null
        const previousTask = _.find(allTasks, t => t['@id'] === task.previous)
        if (previousTask) {
          newTasks.push(previousTask)
        }
        newTasks.push(task)
      } else if (task.next) {
        // If next task is another day, will be null
        const nextTask = _.find(allTasks, t => t['@id'] === task.next)
        newTasks.push(task)
        if (nextTask) {
          newTasks.push(nextTask)
        }
      } else {
        newTasks.push(task)
      }
    })

    dispatch({ type: ASSIGN_TASKS, username, tasks: newTasks })

    const { taskLists } = getState()
    const taskList = _.find(taskLists, taskList => taskList.username === username)

    dispatch(modifyTaskList(username, taskList.items))
  }
}

function addCreatedTask(task) {
  return {type: ADD_CREATED_TASK, task}
}

function removeTasks(username, tasks) {

  return function(dispatch, getState) {

    dispatch({ type: REMOVE_TASKS, username, tasks })

    const { taskLists } = getState()
    const taskList = _.find(taskLists, taskList => taskList.username === username)

    const newTasks = _.differenceWith(
      taskList.items,
      _.intersectionWith(taskList.items, tasks, (a, b) => a['@id'] === b['@id']),
      (a, b) => a['@id'] === b['@id']
    )

    dispatch(modifyTaskList(username, newTasks))
  }
}

function updateTask(task) {
  return {type: UPDATE_TASK, task}
}

function openAddUserModal() {
  return {type: OPEN_ADD_USER}
}

function closeAddUserModal() {
  return {type: CLOSE_ADD_USER}
}

function modifyTaskListRequest(username, tasks) {
  return {type: MODIFY_TASK_LIST_REQUEST, username, tasks}
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

function modifyTaskList(username, tasks) {

  const data = tasks.map((task, index) => {
    return {
      task: task['@id'],
      position: index
    }
  })

  return function(dispatch, getState) {

    const { date } = getState()

    const url = window.Routing.generate('admin_task_list_modify', {
      date: date.format('YYYY-MM-DD'),
      username,
    })

    dispatch(modifyTaskListRequest(username, tasks))

    return fetch(url, {
      credentials: 'include',
      method: 'PUT',
      body: JSON.stringify(data),
      headers: {
        'Content-Type': 'application/json'
      }
    })
      .then(res => res.json())
      .then(taskList => dispatch(modifyTaskListRequestSuccess(taskList)))
  }
}

function togglePolyline(username) {
  return { type: TOGGLE_POLYLINE, username }
}

function toggleTask(task, multiple = false) {
  return { type: TOGGLE_TASK, task, multiple }
}

function selectTask(task) {
  return { type: SELECT_TASK, task }
}

function setTaskListGroupMode(mode) {
  return { type: SET_TASK_LIST_GROUP_MODE, mode }
}

function addTaskListRequest(username) {
  return { type: ADD_TASK_LIST_REQUEST, username }
}

function addTaskListRequestSuccess(taskList) {
  return { type: ADD_TASK_LIST_REQUEST_SUCCESS, taskList }
}

function addTaskList(username) {

  return function(dispatch, getState) {

    const { date } = getState()

    const url = window.Routing.generate('admin_task_list_create', {
      date: date.format('YYYY-MM-DD'),
      username
    })

    dispatch(addTaskListRequest(username))

    return fetch(url, {
      credentials: 'include',
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      }
    })
      .then(res => res.json())
      .then(taskList => dispatch(addTaskListRequestSuccess(taskList)))
  }
}

function setGeolocation(username, coords) {
  return { type: SET_GEOLOCATION, username, coords }
}

function setOffline(username) {
  return { type: SET_OFFLINE, username }
}

function drakeDrag() {
  return { type: DRAKE_DRAG }
}

function drakeDragEnd() {
  return { type: DRAKE_DRAGEND }
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

function loadTaskEventsRequest() {
  return { type: LOAD_TASK_EVENTS_REQUEST }
}

function loadTaskEventsSuccess(task, events) {
  return { type: LOAD_TASK_EVENTS_SUCCESS, task, events }
}

function loadTaskEventsFailure(error) {
  return { type: LOAD_TASK_EVENTS_FAILURE, error }
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

    const url = task.hasOwnProperty('@id') ? task['@id'] : '/api/tasks'
    const method = task.hasOwnProperty('@id') ? 'put' : 'post'

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
        dispatch(updateTask(response.data))
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
        dispatch(updateTask(response.data))
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
        values.forEach(response => dispatch(updateTask(response.data)))
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
        dispatch(updateTask(response.data))
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
  updateTask,
  addTaskList,
  modifyTaskList,
  assignTasks,
  removeTasks,
  openAddUserModal,
  closeAddUserModal,
  togglePolyline,
  setTaskListGroupMode,
  addCreatedTask,
  toggleTask,
  selectTask,
  setGeolocation,
  setOffline,
  drakeDrag,
  drakeDragEnd,
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
}
