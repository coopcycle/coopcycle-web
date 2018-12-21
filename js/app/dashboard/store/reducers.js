import { combineReducers } from 'redux'
import _ from 'lodash'
import moment from 'moment'

const taskComparator = (taskA, taskB) => taskA['@id'] === taskB['@id']

const dateInitial = window.AppData && window.AppData.Dashboard ? window.AppData.Dashboard.date : moment()

let tasksInitial = []
let taskListsInitial = []

// initial data pumped from the template
if (window.AppData && window.AppData.Dashboard) {
  tasksInitial = window.AppData.Dashboard.tasks
  taskListsInitial = window.AppData.Dashboard.taskLists
}

const unassignedTasksInitial = _.filter(tasksInitial, task => !task.isAssigned)

unassignedTasksInitial.sort((a, b) => {
  const doneBeforeA = moment(a.doneBefore)
  const doneBeforeB = moment(b.doneBefore)

  return doneBeforeA.isBefore(doneBeforeB) ? -1 : 1
})

let polylineEnabledByUser = {}
_.forEach(taskListsInitial, taskList => {
  polylineEnabledByUser[taskList.username] = false
})

const replaceOrAddTask = (tasks, task) => {

  const taskIndex = _.findIndex(tasks, t => t['@id'] === task['@id'])

  if (-1 !== taskIndex) {

    const newTasks = tasks.slice(0)
    newTasks.splice(taskIndex, 1, Object.assign({}, tasks[taskIndex], task))

    return newTasks
  }

  return tasks.concat([ task ])
}

const selectedTasksInitial = []

export const taskLists = (state = taskListsInitial, action) => {

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

    if (!moment(action.task.doneBefore).isSame(dateInitial, 'day')) {
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

  case 'UPDATE_TASK':

    taskListIndex = _.findIndex(state, taskList => {
      const taskIds = _.map(taskList.items, task => task['@id'])
      return _.includes(taskIds, action.task['@id'])
    })

    if (action.task.isAssigned) {

      targetTaskListIndex = _.findIndex(state, taskList => taskList.username === action.task.assignedTo)

      if (-1 !== taskListIndex) {
        if (targetTaskListIndex !== taskListIndex) {
          newTaskLists.splice(taskListIndex, 1, {
            ...state[taskListIndex],
            items: _.filter(state[taskListIndex].items, item => item['@id'] !== action.task['@id'])
          })
        }
      }

      if (-1 !== targetTaskListIndex) {
        newTaskLists.splice(targetTaskListIndex, 1, {
          ...state[targetTaskListIndex],
          items: replaceOrAddTask(state[targetTaskListIndex].items, action.task)
        })
      }

    } else {
      if (-1 !== taskListIndex) {
        newTaskLists.splice(taskListIndex, 1, {
          ...state[taskListIndex],
          items: _.filter(state[taskListIndex].items, item => item['@id'] !== action.task['@id'])
        })
      }
    }

    return newTaskLists
  }

  return state
}

/*
  Store for all unassigned tasks
 */
export const unassignedTasks = (state = unassignedTasksInitial, action) => {
  let newState

  switch (action.type) {

  case 'ADD_CREATED_TASK':
    if (!moment(action.task.doneBefore).isSame(dateInitial, 'day')) {
      return state
    }
    if (!_.find(unassignedTasksInitial, (task) => { task['id'] === action.task.id })) {
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

  case 'UPDATE_TASK':

    newState = state.slice(0)

    let taskIndex = _.findIndex(newState, task => action.task['@id'] === task['@id'])

    if (-1 !== taskIndex) {

      // If the task has been assigned, remove it
      // If the task new due date is different from the one displayed, remove it
      if (action.task.isAssigned || !moment(action.task.doneBefore).isSame(dateInitial, 'day')) {
        newState = _.differenceWith(
          newState,
          _.intersectionWith(newState, [ action.task ], taskComparator),
          taskComparator
        )
      // If the task is still unassigned, just replace it
      } else {
        // let taskIndex = _.findIndex(newState, task => action.task['@id'] === task['@id'])
        newState.splice(taskIndex, 1, Object.assign({}, action.task))
      }

      return newState
    } else {
      if (!action.task.isAssigned) {
        return state.concat([ action.task ])
      }
    }
  }

  return state
}

export const allTasks = (state = tasksInitial, action) => {
  let newState

  switch (action.type) {

  case 'ADD_CREATED_TASK':
    if (!moment(action.task.doneBefore).isSame(dateInitial, 'day')) {
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

export const polylineEnabled = (state = polylineEnabledByUser, action) => {
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

export const selectedTasks = (state = selectedTasksInitial, action) => {

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

export const tagsFilter = (state = { selectedTagsList: window.AppData.Dashboard.tags, showUntaggedTasks: true }, action) => {

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

export const jwt = (state = '', action) => {
  switch (action.type) {
  default:

    return state
  }
}

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
    break

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

export default combineReducers({
  allTasks,
  unassignedTasks,
  taskLists,
  taskListsLoading,
  addModalIsOpen,
  polylineEnabled,
  taskListGroupMode,
  taskFinishedFilter,
  taskCancelledFilter,
  tagsFilter,
  selectedTasks,
  jwt,
  positions,
  offline,
  isDragging
})
