import { combineReducers } from 'redux'
import _ from 'lodash'
import moment from 'moment'

const taskComparator = (taskA, taskB) => taskA['@id'] === taskB['@id']

// initial data pumped from the template
const tasksInitial = window.AppData.Dashboard.tasks,
      taskListsInitial = window.AppData.Dashboard.taskLists,
      unassignedTasksInitial = _.filter(tasksInitial, task => !task.isAssigned)

unassignedTasksInitial.sort((a, b) => {
  const doneBeforeA = moment(a.doneBefore)
  const doneBeforeB = moment(b.doneBefore)

  return doneBeforeA.isBefore(doneBeforeB) ? -1 : 1
})

let polylineEnabledByUser = {}
_.forEach(taskLists, taskList => {
  polylineEnabledByUser[taskList.username] = false
})

const selectedTasksInitial = []

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
        Object.assign({}, action.taskList, { items: action.taskList.items }))

      return newTaskLists

    case 'ADD_TASK_LIST_REQUEST_SUCCESS':

      newTaskLists.push(action.taskList)

      return newTaskLists

    case 'ADD_CREATED_TASK':

      if (!moment(action.task.doneBefore).isSame(window.AppData.Dashboard.date, 'day')) {
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
      break;

    case 'UPDATE_TASK':

      // Task new due date is different from the one displayed -> reload to hide task
      if (!moment(action.task.doneBefore).isSame(window.AppData.Dashboard.date, 'day')) {
        window.location.reload()
      }

      // The task may have been assigned through the modal
      // We need to lookup all the lists as we don't know if it was assigned or not
      taskListIndex = _.findIndex(newTaskLists, taskList => {
        const taskIds = _.map(taskList.items, task => task['@id'])
        return _.includes(taskIds, action.task['@id'])
      })

      // The task belongs to a list
      if (-1 !== taskListIndex) {

        // If the task is still assigned, replace it
        if (action.task.isAssigned) {

          taskListIndex = _.findIndex(newTaskLists, taskList => taskList.username === action.task.assignedTo)
          taskList = newTaskLists[taskListIndex]
          taskListItems = taskList.items.slice(0)

          const taskIndex = _.findIndex(taskList.items, task => action.task['@id'] === task['@id'])
          taskListItems.splice(taskIndex, 1, action.task)
          newTaskLists.splice(taskListIndex, 1,
            Object.assign({}, taskList, { items: taskListItems }))

        // If the task has been unassigned, remove it
        } else {

          taskList = newTaskLists[taskListIndex]

          taskListItems = _.differenceWith(
            taskList.items,
            _.intersectionWith(taskList.items, [ action.task ], taskComparator),
            taskComparator
          )
          newTaskLists.splice(taskListIndex, 1,
            Object.assign({}, taskList, { items: taskListItems }))
        }

        return newTaskLists

      } else {

        if (action.task.isAssigned) {

          // FIXME
          // The task has been assigned through the modal
          // Given our architecture, it is simpler to reload the page
          // because there may be linked tasks that have been assigned
          // It would be more reliable to rely on data from the server to update the dashboard
          window.location.reload()

        }

      }
  }

  return state
}

/*
  Store for all unassigned tasks
 */
const unassignedTasks = (state = unassignedTasksInitial, action) => {
  let newState

  switch (action.type) {

    case 'ADD_CREATED_TASK':
      if (!moment(action.task.doneBefore).isSame(window.AppData.Dashboard.date, 'day')) {
        return state
      }
      if (!_.find(unassignedTasksInitial, (task) => { task['id'] === action.task.id })) {
        newState = state.slice(0)
        return Array.prototype.concat(newState, [ action.task ])
      }
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

      // Task new due date is different from the one displayed -> reload to hide task
      if (!moment(action.task.doneBefore).isSame(window.AppData.Dashboard.date, 'day')) {
        window.location.reload()
      }

      newState = state.slice(0)

      let taskIndex = _.findIndex(newState, task => action.task['@id'] === task['@id'])

      if (-1 !== taskIndex) {

        // If the task has been assigned, remove it
        if (action.task.isAssigned) {
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
      }
  }

  return state
}

const allTasks = (state = tasksInitial, action) => {
  let newState

  switch (action.type) {

    case 'ADD_CREATED_TASK':
      if (!moment(action.task.doneBefore).isSame(window.AppData.Dashboard.date, 'day')) {
        return state
      }

      newState = state.slice(0)
      return Array.prototype.concat(newState, [ action.task ])

    // case 'UPDATE_TASK':
    //   break;
  }

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

const selectedTasks = (state = selectedTasksInitial, action) => {

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

const taskListGroupMode = (state = 'GROUP_MODE_FOLDERS', action) => {
  switch (action.type) {
    case 'SET_TASK_LIST_GROUP_MODE':
      return action.mode
    default:
      return state
  }
}

const taskFinishedFilter = (state = true, action) => {
  switch (action.type) {
    case 'TOGGLE_SHOW_FINISHED_TASKS':
      let showFinishedTasks = !state
      return showFinishedTasks
    default:
      return state
  }
}

const taskCancelledFilter = (state = false, action) => {
  switch (action.type) {
    case 'TOGGLE_SHOW_CANCELLED_TASKS':
      let showCancelledTasks = !state
      return showCancelledTasks
    default:
      return state
  }
}

const tagsFilter = (state = { selectedTagsList: window.AppData.Dashboard.tags, showUntaggedTasks: true }, action ) => {

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

const jwt = (state = '', action) => {
  switch (action.type) {
    default:

      return state
  }
}

const positions = (state = [], action) => {
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

const offline = (state = [], action) => {
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

const isDragging = (state = false, action) => {
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
