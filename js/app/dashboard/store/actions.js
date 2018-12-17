function assignTasks(username, tasks) {
  return {type: 'ASSIGN_TASKS', username, tasks}
}

function addCreatedTask(task) {
  return {type: 'ADD_CREATED_TASK', task}
}

function removeTasks(username, tasks) {
  return {type: 'REMOVE_TASKS', username, tasks}
}

function updateTask(task) {
  return {type: 'UPDATE_TASK', task}
}

function openAddUserModal() {
  return {type: 'OPEN_ADD_USER'}
}

function closeAddUserModal() {
  return {type: 'CLOSE_ADD_USER'}
}

function modifyTaskListRequest(username, tasks) {
  return {type: 'MODIFY_TASK_LIST_REQUEST', username, tasks}
}

function modifyTaskListRequestSuccess(taskList) {
  return { type: 'MODIFY_TASK_LIST_REQUEST_SUCCESS', taskList }
}

function toggleShowFinishedTasks() {
  return { type: 'TOGGLE_SHOW_FINISHED_TASKS' }
}

function toggleShowUntaggedTasks() {
  return { type: 'TOGGLE_SHOW_UNTAGGED_TASKS' }
}

function toggleShowCancelledTasks() {
  return { type: 'TOGGLE_SHOW_CANCELLED_TASKS' }
}

function setSelectedTagList (tag) {
  return {type: 'FILTER_TAG_BY_TAGNAME', tag: tag }
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
  return { type: 'TOGGLE_POLYLINE', username }
}

function toggleTask(task, multiple = false) {
  return { type: 'TOGGLE_TASK', task, multiple }
}

function selectTask(task) {
  return { type: 'SELECT_TASK', task }
}

function setTaskListGroupMode(mode) {
  return { type: 'SET_TASK_LIST_GROUP_MODE', mode }
}

function addTaskListRequest(username) {
  return { type: 'ADD_TASK_LIST_REQUEST', username }
}

function addTaskListRequestSuccess(taskList) {
  return { type: 'ADD_TASK_LIST_REQUEST_SUCCESS', taskList }
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
  return { type: 'SET_GEOLOCATION', username, coords }
}

function setOffline(username) {
  return { type: 'SET_OFFLINE', username }
}

function drakeDrag() {
  return { type: 'DRAKE_DRAG' }
}

function drakeDragEnd() {
  return { type: 'DRAKE_DRAGEND' }
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
