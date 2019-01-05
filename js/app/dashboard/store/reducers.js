import { combineReducers } from 'redux'
import _ from 'lodash'
import moment from 'moment'

const taskComparator = (taskA, taskB) => taskA['@id'] === taskB['@id']

const replaceOrAddTask = (tasks, task) => {

  const taskIndex = _.findIndex(tasks, t => t['@id'] === task['@id'])

  if (-1 !== taskIndex) {

    const newTasks = tasks.slice(0)
    newTasks.splice(taskIndex, 1, Object.assign({}, tasks[taskIndex], task))

    return newTasks
  }

  return tasks.concat([ task ])
}

const removeTask = (tasks, task) => _.filter(tasks, t => t['@id'] !== task['@id'])

const initialState = {

  allTasks: [],
  unassignedTasks: [],
  taskLists: [],
  date: moment(),

  taskListsLoading: false,
  addModalIsOpen: false,
  polylineEnabled: {},
  taskListGroupMode: 'GROUP_MODE_FOLDERS',
  taskFinishedFilter: true,
  taskCancelledFilter: false,
  tags: [],
  tagsFilter: {
    selectedTagsList: [],
    showUntaggedTasks: true
  },
  selectedTasks: [],
  jwt: '',
  positions: [],
  offline: [],
  isDragging: false
}

const rootReducer = (state = initialState, action) => {
  switch (action.type) {
  case 'UPDATE_TASK':

    let newUnassignedTasks = state.unassignedTasks.slice(0)
    let newTaskLists = state.taskLists.slice(0)

    let unassignedTasksIndex = _.findIndex(state.unassignedTasks, task => task['@id'] === action.task['@id'])
    let taskListsIndex = _.findIndex(state.taskLists, taskList => {
      return _.includes(_.map(taskList.items, task => task['@id']), action.task['@id'])
    })

    if (-1 !== unassignedTasksIndex) {
      if (action.task.isAssigned) {
        newUnassignedTasks = removeTask(state.unassignedTasks, action.task)
      } else {
        newUnassignedTasks = replaceOrAddTask(state.unassignedTasks, action.task)
      }
    } else {
      if (!action.task.isAssigned) {
        newUnassignedTasks = replaceOrAddTask(state.unassignedTasks, action.task)
      }
    }

    if (action.task.isAssigned) {

      let targetTaskListsIndex = _.findIndex(state.taskLists, taskList => taskList.username === action.task.assignedTo)

      if (-1 !== taskListsIndex) {
        if (targetTaskListsIndex !== taskListsIndex) {
          newTaskLists.splice(taskListsIndex, 1, {
            ...state.taskLists[taskListsIndex],
            items: removeTask(state.taskLists[taskListsIndex].items, action.task)
          })
        }
      }

      if (-1 !== targetTaskListsIndex) {
        newTaskLists.splice(targetTaskListsIndex, 1, {
          ...state.taskLists[targetTaskListsIndex],
          items: replaceOrAddTask(state.taskLists[targetTaskListsIndex].items, action.task)
        })
      }

    } else {
      if (-1 !== taskListsIndex) {
        newTaskLists.splice(taskListsIndex, 1, {
          ...state.taskLists[taskListsIndex],
          items: removeTask(state.taskLists[taskListsIndex].items, action.task)
        })
      }
    }

    return {
      ...state,
      unassignedTasks: newUnassignedTasks,
      taskLists: newTaskLists,
    }
  }

  return state
}

function _taskLists(state = [], action, date = initialState.date) {

  let newTaskLists = state.slice(0)
  let taskListIndex
  let taskList
  let taskListItems
  let targetTaskListIndex

  switch (action.type) {

  case 'ASSIGN_TASKS':

    taskListIndex = _.findIndex(newTaskLists, taskList => taskList.username === action.username)
    taskList = newTaskLists[taskListIndex]

    newTaskLists.splice(taskListIndex, 1,
      Object.assign({}, taskList, { items: taskList.items.concat(action.tasks) }))

    return newTaskLists

  case 'REMOVE_TASKS':

    taskListIndex = _.findIndex(newTaskLists, taskList => taskList.username === action.username)
    taskList = newTaskLists[taskListIndex]

    taskListItems = _.differenceWith(
      taskList.items,
      _.intersectionWith(taskList.items, action.tasks, taskComparator),
      taskComparator
    )
    newTaskLists.splice(taskListIndex, 1,
      Object.assign({}, taskList, { items: taskListItems }))

    return newTaskLists

  case 'MODIFY_TASK_LIST_REQUEST_SUCCESS':

    taskListIndex = _.findIndex(newTaskLists, taskList => taskList['@id'] === action.taskList['@id'])

    newTaskLists.splice(taskListIndex, 1,
      Object.assign({}, action.taskList, { items: action.taskList.items }))

    return newTaskLists

  case 'ADD_TASK_LIST_REQUEST_SUCCESS':

    newTaskLists.push(action.taskList)

    return newTaskLists

  case 'ADD_CREATED_TASK':

    if (!moment(action.task.doneBefore).isSame(date, 'day')) {
      return newTaskLists
    }

    if (action.task.isAssigned) {
      taskListIndex = _.findIndex(newTaskLists, taskList => taskList.username === action.task.assignedTo)

      if (taskListIndex && !_.find(taskList.items, (task) => { task['id'] === action.task.id })) {
        taskList = newTaskLists[taskListIndex]
        taskListItems = Array.prototype.concat(taskList.items, [action.task])
        newTaskLists.splice(taskListIndex, 1,
          Object.assign({}, taskList, { items: taskListItems })
        )
        return newTaskLists
      } else {
        // TODO : create a new TaskList object
        window.location.reload()
      }
    }
    break
  }

  return state
}

/*
  Store for all unassigned tasks
 */
function _unassignedTasks(state = [], action, date = initialState.date) {
  let newState

  switch (action.type) {

  case 'ADD_CREATED_TASK':
    if (!moment(action.task.doneBefore).isSame(date, 'day')) {
      return state
    }
    if (!_.find(state, (task) => { task['id'] === action.task.id })) {
      newState = state.slice(0)
      return Array.prototype.concat(newState, [ action.task ])
    }
    break

  case 'ASSIGN_TASKS':
    newState = state.slice(0)
    newState = _.differenceWith(
      newState,
      _.intersectionWith(newState, action.tasks, taskComparator),
      taskComparator
    )
    return newState

  case 'REMOVE_TASKS':
    return Array.prototype.concat(state, action.tasks)
  }

  return state
}

function _allTasks(state = [], action, date = initialState.date) {
  let newState

  switch (action.type) {

  case 'ADD_CREATED_TASK':
    if (!moment(action.task.doneBefore).isSame(date, 'day')) {
      return state
    }

    newState = state.slice(0)
    return Array.prototype.concat(newState, [ action.task ])

  // case 'UPDATE_TASK':
  //   break;
  }

  return state
}

export const addModalIsOpen = (state = false, action) => {
  switch(action.type) {
  case 'OPEN_ADD_USER':
    return true
  case 'CLOSE_ADD_USER':
    return false
  default:
    return state
  }
}

export const taskListsLoading = (state = false, action) => {
  switch(action.type) {
  case 'ADD_TASK_LIST_REQUEST':
  case 'MODIFY_TASK_LIST_REQUEST':
    return true
  case 'ADD_TASK_LIST_REQUEST_SUCCESS':
  case 'MODIFY_TASK_LIST_REQUEST_SUCCESS':
    return false
  case 'SAVE_USER_TASKS_ERROR':
    throw(new Error('Unhnadled error case for save'))
  default:
    return state
  }
}

export const polylineEnabled = (state = {}, action) => {
  switch (action.type) {
  case 'TOGGLE_POLYLINE':
    let newState = { ...state }
    const { username } = action
    newState[username] = !state[username]

    return newState
  default:
    return state
  }
}

export const selectedTasks = (state = [], action) => {

  let newState = state.slice(0)

  switch (action.type) {
  case 'TOGGLE_TASK':

    if (-1 !== state.indexOf(action.task)) {
      if (!action.multiple) {
        return []
      }
      return _.filter(state, task => task !== action.task)
    }

    if (!action.multiple) {
      newState = []
    }
    newState.push(action.task)

    return newState

  case 'SELECT_TASK':

    if (-1 !== state.indexOf(action.task)) {

      return state
    }

    return [ action.task ]
  }

  return state
}

export const taskListGroupMode = (state = 'GROUP_MODE_FOLDERS', action) => {
  switch (action.type) {
  case 'SET_TASK_LIST_GROUP_MODE':
    return action.mode
  default:
    return state
  }
}

export const taskFinishedFilter = (state = true, action) => {
  switch (action.type) {
  case 'TOGGLE_SHOW_FINISHED_TASKS':
    let showFinishedTasks = !state
    return showFinishedTasks
  default:
    return state
  }
}

export const taskCancelledFilter = (state = false, action) => {
  switch (action.type) {
  case 'TOGGLE_SHOW_CANCELLED_TASKS':
    let showCancelledTasks = !state
    return showCancelledTasks
  default:
    return state
  }
}

export const tags = (state = initialState.tags, action) => state

export const tagsFilter = (state = { selectedTagsList: [], showUntaggedTasks: true }, action) => {

  switch (action.type) {

  case 'FILTER_TAG_BY_TAGNAME':
    let selectedTagsList = state.selectedTagsList.slice(0)

    if (_.find(selectedTagsList, tag => tag.name === action.tag.name)) {
      // removing the tag from visible list
      selectedTagsList = _.filter(selectedTagsList, tag => tag.name != action.tag.name)
    } else {
      // adding the tag to visible list
      selectedTagsList.push(action.tag)
    }
    return {...state, selectedTagsList}
  case 'TOGGLE_SHOW_UNTAGGED_TASKS':
    let showUntaggedTasks = !state.showUntaggedTasks
    return {...state, showUntaggedTasks}
  default:
    return state
  }
}

export const jwt = (state = '', action) => state

export const date = (state = moment(), action) => state

export const positions = (state = [], action) => {
  switch (action.type) {
  case 'SET_GEOLOCATION':

    const marker = {
      username: action.username,
      coords: action.coords,
      lastSeen: moment()
    }

    const newState = state.slice(0)
    const index = _.findIndex(newState, position => position.username === action.username)
    if (-1 !== index) {
      newState.splice(index, 1, marker)
    } else {
      newState.push(marker)
    }

    return newState

  default:

    return state
  }
}

export const offline = (state = [], action) => {
  let index

  switch (action.type) {
  case 'SET_GEOLOCATION':

    index = _.findIndex(state, username => username === action.username)
    if (-1 === index) {

      return state
    }

    return _.filter(state, username => username !== action.username)

  case 'SET_OFFLINE':

    index = _.findIndex(state, username => username === action.username)
    if (-1 === index) {

      return state.concat([ action.username ])
    }

  default:

    return state
  }
}

export const isDragging = (state = false, action) => {
  switch (action.type) {
  case 'DRAKE_DRAG':

    return true

  case 'DRAKE_DRAGEND':

    return false

  default:

    return state
  }
}

export const combinedTasks = (state = initialState, action) => {

  switch (action.type) {

  case 'ADD_CREATED_TASK':

    return {
      ...state,
      unassignedTasks: _unassignedTasks(state.unassignedTasks, action, state.date),
      taskLists: _taskLists(state.taskLists, action, state.date),
      allTasks: _allTasks(state.allTasks, action, state.date)
    }
  case 'UPDATE_TASK':

    const { unassignedTasks, taskLists } = rootReducer(state, action)

    return {
      ...state,
      unassignedTasks,
      taskLists,
    }
  }

  return {
    ...state,
    unassignedTasks: _unassignedTasks(state.unassignedTasks, action),
    taskLists: _taskLists(state.taskLists, action),
    allTasks: _allTasks(state.allTasks, action)
  }
}

export default (state = initialState, action) => {

  const { allTasks, unassignedTasks, taskLists } = combinedTasks(state, action)

  return {
    ...state,
    unassignedTasks,
    taskLists,
    allTasks,
    taskListsLoading: taskListsLoading(state.taskListsLoading, action),
    addModalIsOpen: addModalIsOpen(state.addModalIsOpen, action),
    polylineEnabled: polylineEnabled(state.polylineEnabled, action),
    taskListGroupMode: taskListGroupMode(state.taskListGroupMode, action),
    taskFinishedFilter: taskFinishedFilter(state.taskFinishedFilter, action),
    taskCancelledFilter: taskCancelledFilter(state.taskCancelledFilter, action),
    tags: tags(state.tags, action),
    tagsFilter: tagsFilter(state.tagsFilter, action),
    selectedTasks: selectedTasks(state.selectedTasks, action),
    jwt: jwt(state.jwt, action),
    date: date(state.date, action),
    positions: positions(state.positions, action),
    offline: offline(state.offline, action),
    isDragging: isDragging(state.isDragging, action)
  }
}
