import _ from 'lodash'

export const ASSIGN_TASKS = 'ASSIGN_TASKS'
export const ADD_CREATED_TASK = 'ADD_CREATED_TASK'
export const REMOVE_TASKS = 'REMOVE_TASKS'
export const UPDATE_TASK = 'UPDATE_TASK'
export const OPEN_ADD_USER = 'OPEN_ADD_USER'
export const CLOSE_ADD_USER = 'CLOSE_ADD_USER'
export const MODIFY_TASK_LIST_REQUEST = 'MODIFY_TASK_LIST_REQUEST'
export const MODIFY_TASK_LIST_REQUEST_SUCCESS = 'MODIFY_TASK_LIST_REQUEST_SUCCESS'
export const TOGGLE_SHOW_FINISHED_TASKS = 'TOGGLE_SHOW_FINISHED_TASKS'
export const TOGGLE_SHOW_UNTAGGED_TASKS = 'TOGGLE_SHOW_UNTAGGED_TASKS'
export const TOGGLE_SHOW_CANCELLED_TASKS = 'TOGGLE_SHOW_CANCELLED_TASKS'
export const FILTER_TAG_BY_TAGNAME = 'FILTER_TAG_BY_TAGNAME'
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

function assignTasks(username, tasks) {

  return function(dispatch, getState) {

    dispatch({ type: ASSIGN_TASKS, username, tasks })

    const { taskLists } = getState()
    const taskList = _.find(taskLists, taskList => taskList.username === username)

    dispatch(modifyTaskList(username, taskList.items.concat(tasks)))
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

function _updateTask(task) {
  return {type: UPDATE_TASK, task}
}

function updateTask(task) {
  return function(dispatch, getState) {

    if (task.isAssigned) {
      const targetTaskList = _.find(getState().taskLists, taskList => taskList.username === task.assignedTo)

      // The target TaskList does not exist (yet), we reload the page
      if (!targetTaskList) {
        window.location.reload()

        return
      }
    }

    dispatch(_updateTask(task))
  }
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

function toggleShowFinishedTasks() {
  return { type: TOGGLE_SHOW_FINISHED_TASKS }
}

function toggleShowUntaggedTasks() {
  return { type: TOGGLE_SHOW_UNTAGGED_TASKS }
}

function toggleShowCancelledTasks() {
  return { type: TOGGLE_SHOW_CANCELLED_TASKS }
}

function setSelectedTagList (tag) {
  return {type: FILTER_TAG_BY_TAGNAME, tag: tag }
}

function modifyTaskList(username, tasks) {
  const url = window.AppData.Dashboard.modifyTaskListURL.replace('__USERNAME__', username)
  const data = tasks.map((task, index) => {
    return {
      task: task['@id'],
      position: index
    }
  })

  return function(dispatch) {
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
  const url = window.AppData.Dashboard.createTaskListURL.replace('__USERNAME__', username)

  return function(dispatch) {
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

export {
  setSelectedTagList,
  updateTask,
  addTaskList,
  modifyTaskList,
  assignTasks,
  removeTasks,
  openAddUserModal,
  closeAddUserModal,
  togglePolyline,
  setTaskListGroupMode,
  toggleShowFinishedTasks,
  toggleShowUntaggedTasks,
  toggleShowCancelledTasks,
  addCreatedTask,
  toggleTask,
  selectTask,
  setGeolocation,
  setOffline,
  drakeDrag,
  drakeDragEnd
}
