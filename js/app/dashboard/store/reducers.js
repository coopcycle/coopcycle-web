import { combineReducers } from 'redux'
import _ from 'lodash'

function addLinkProperty(tasks) {
  let tasksById = _.keyBy(tasks, task => task['@id'])

  const tasksWithPrevious = _.filter(tasks, task => task.previous !== null)
  _.each(tasksWithPrevious, task => {
    const previousTask = tasksById[task.previous]
    const taskArray    = [ previousTask, task ]
    const linkKey      = _.join(_.map(taskArray, task => task.id), ':')

    tasksById[task.previous] = Object.assign(tasksById[task.previous], { link: linkKey })
    tasksById[task['@id']]   = Object.assign(tasksById[task['@id']], { link: linkKey })
  })

  return _.map(tasksById, task => task)
}

// initial data pumped from the template
const tasksInitial = addLinkProperty(window.AppData.Dashboard.tasks),
      unassignedTasksInitial = _.filter(tasksInitial, task => !task.isAssigned),
      assignedTasksList = _.filter(tasksInitial, task => task.isAssigned),
      assignedTasksByUserInitial = _.groupBy(assignedTasksList, task => task.assignedTo)

_.each(_.keys(assignedTasksByUserInitial),
      (username) => {
        if (window.AppData.Dashboard.taskLists.hasOwnProperty(username)) {
          const { distance, duration, polyline } = window.AppData.Dashboard.taskLists[username]
          assignedTasksByUserInitial[username].duration = duration
          assignedTasksByUserInitial[username].distance = distance
          assignedTasksByUserInitial[username].polyline = polyline
        } else {
          assignedTasksByUserInitial[username].duration = 0
          assignedTasksByUserInitial[username].distance = 0
          assignedTasksByUserInitial[username].polyline = ''
        }
      })

let polylineEnabledByUser = {}
_.each(_.keys(assignedTasksByUserInitial), username => {
  polylineEnabledByUser[username] = false
})

/*
  Store for all assigned tasks
*/
const assignedTasksByUser = (state = assignedTasksByUserInitial, action) => {

  let  userTasks = state[action.username],
    newState = { ...state },
    newUserTasks,
    taskComparator = (taskA, taskB) => taskA['@id'] === taskB['@id']

  switch(action.type) {
    case 'ASSIGN_TASKS':
      newUserTasks = userTasks.slice()
      // backend need to have the `position` attribute correctly set - append new tasks
      let position = newUserTasks[newUserTasks.length - 1] ? newUserTasks[newUserTasks.length - 1].position : -1
      _.each(action.tasks, (task) => {
        position++
        task.position = position
      })

      newState[action.username] = Array.prototype.concat(newUserTasks, action.tasks)
      newState[action.username].duration = 0
      newState[action.username].distance = 0
      newState[action.username].polyline = ''
      break
    case 'REMOVE_TASKS':
      newUserTasks  = _.differenceWith(
        userTasks,
        _.intersectionWith(userTasks, action.tasks, taskComparator),
        taskComparator
      )
      newState[action.username] = newUserTasks
      newState[action.username].duration = 0
      newState[action.username].distance = 0
      newState[action.username].polyline = ''
      break
    case 'ADD_USERNAME':
      newState[action.username] = []
      newState[action.username].duration = 0
      newState[action.username].distance = 0
      newState[action.username].polyline = ''
      break
    case 'SAVE_USER_TASKS_SUCCESS':
      newState[action.username] = addLinkProperty(action.tasks)
      newState[action.username].duration = action.duration
      newState[action.username].distance = action.distance
      newState[action.username].polyline = action.polyline
      break
    case 'UPDATE_TASK':
      if (action.task.assignedTo) {
        userTasks = state[action.task.assignedTo]
        let index =  _.findIndex(userTasks, (task) => action.task['@id'] === task['@id'])
        userTasks.splice(index, 1, action.task)
        newState[action.task.assignedTo] =  userTasks
      }
      break
  }

  return newState
}

/*
  Store for all unassigned tasks
 */
const unassignedTasks = (state = unassignedTasksInitial, action) => {
  let newState = state.slice(),
    taskComparator = (taskA, taskB) => taskA['@id'] === taskB['@id']

  switch(action.type) {
    case 'ASSIGN_TASKS':
      newState  = _.differenceWith(
        newState,
        _.intersectionWith(newState, action.tasks, taskComparator),
        taskComparator
        )
      break
    case 'REMOVE_TASKS':
      newState = Array.prototype.concat(newState, action.tasks)
      break
    case 'UPDATE_TASK':
      if (!action.task.assignedTo) {
        let index =  _.findIndex(newState, (task) => action.task['@id'] === task['@id'])
        newState.splice(index, 1, action.task)
      }
      break
  }

  return newState
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

const userPanelLoading = (state = false, action) => {
  switch(action.type) {
    case 'SAVE_USER_TASKS':
      return true
    case 'SAVE_USER_TASKS_SUCCESS':
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
  assignedTasksByUser,
  unassignedTasks,
  userPanelLoading,
  addModalIsOpen,
  polylineEnabled,
  taskListGroupMode,
})
