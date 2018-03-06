import { combineReducers } from 'redux'
import _ from 'lodash'

function addLinkProperty(tasks) {
  let tasksById = _.keyBy(tasks, task => task['@id'])

  const tasksWithPrevious = _.filter(tasks, task => task.previous !== null)
  _.each(tasksWithPrevious, task => {
    const previousTask = tasksById[task.previous]

    // previous task may be undefined (example case: two different days)
    if (previousTask) {
      const taskArray    = [ previousTask, task ],
        linkKey      = _.join(_.map(taskArray, task => task.id), ':')

      tasksById[task.previous] = Object.assign(tasksById[task.previous], { link: linkKey })
      tasksById[task['@id']]   = Object.assign(tasksById[task['@id']], { link: linkKey })
    }
  })

  return _.map(tasksById, task => task)
}

const taskComparator = (taskA, taskB) => taskA['@id'] === taskB['@id']

// initial data pumped from the template
const tasksInitial = addLinkProperty(window.AppData.Dashboard.tasks),
      taskListsInitial = window.AppData.Dashboard.taskLists.map(taskList =>
        Object.assign(taskList, { items: addLinkProperty(taskList.items) })
      ),
      unassignedTasksInitial = _.filter(tasksInitial, task => !task.isAssigned)

let polylineEnabledByUser = {}
_.forEach(taskLists, taskList => {
  polylineEnabledByUser[taskList.username] = false
})

const taskLists = (state = taskListsInitial, action) => {

  let newTaskLists = state.slice(0)
  let taskListIndex
  let taskList
  let taskListItems

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
        Object.assign({}, action.taskList, { items: addLinkProperty(action.taskList.items) }))

      return newTaskLists

    case 'ADD_TASK_LIST_REQUEST_SUCCESS':

      newTaskLists.push(action.taskList)

      return newTaskLists

    case 'UPDATE_TASK':
      if (action.task.assignedTo) {

        taskListIndex = _.findIndex(newTaskLists, taskList => taskList.username === action.task.assignedTo)
        taskList = newTaskLists[taskListIndex]

        const taskIndex = _.findIndex(taskList.items, task => action.task['@id'] === task['@id'])

        taskListItems = taskList.items.slice(0)
        taskListItems.splice(taskIndex, 1, action.task)

        newTaskLists.splice(taskListIndex, 1,
          Object.assign({}, taskList, { items: taskListItems }))

        return newTaskLists
      }
      break
  }

  return state
}

/*
  Store for all unassigned tasks
 */
const unassignedTasks = (state = unassignedTasksInitial, action) => {
  let newState

  switch (action.type) {

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
      if (!action.task.assignedTo) {
        newState = state.slice(0)
        let index = _.findIndex(newState, (task) => action.task['@id'] === task['@id'])
        newState.splice(index, 1, action.task)

        return newState
      }
  }

  return state
}

const allTasks = (state = tasksInitial, action) => {
  return state
}

const addModalIsOpen = (state = false, action) => {
  switch(action.type) {
    case 'OPEN_ADD_USER':
      return true
    case 'CLOSE_ADD_USER':
      return false
    default:
      return state
  }
}

const taskListsLoading = (state = false, action) => {
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

const polylineEnabled = (state = polylineEnabledByUser, action) => {
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

const taskListGroupMode = (state = 'GROUP_MODE_FOLDERS', action) => {
  switch (action.type) {
    case 'SET_TASK_LIST_GROUP_MODE':
      return action.mode
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
})
