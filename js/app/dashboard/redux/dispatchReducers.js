import _ from 'lodash'
import { moment } from 'coopcycle-frontend-js'

import { createTaskList, removedTasks, withoutTasks } from './utils'
import {
  UPDATE_TASK,
  MODIFY_TASK_LIST_REQUEST,
  MODIFY_TASK_LIST_REQUEST_SUCCESS,
  TASK_LIST_UPDATED,
  ADD_TASK_LIST_REQUEST,
  ADD_TASK_LIST_REQUEST_SUCCESS,
  SET_TASK_LISTS_LOADING,
} from './actions'

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

const initialState = {
  taskListsLoading: false,
}

const rootReducer = (state = initialState, action) => {
  let newTaskLists = state.taskLists.slice(0)
  let taskListIndex

  switch (action.type) {
  case MODIFY_TASK_LIST_REQUEST:

    taskListIndex = _.findIndex(state.taskLists, taskList => taskList.username === action.username)
    newTaskLists.splice(taskListIndex, 1, {
      ...state.taskLists[taskListIndex],
      items: action.tasks,
    })

    let removed = removedTasks(state.taskLists[taskListIndex].items, action.tasks)

    return {
      ...state,
      taskListsLoading: true,
      taskLists: newTaskLists,
      unassignedTasks: withoutTasks(
        Array.prototype.concat(state.unassignedTasks, removed),
        action.tasks
      ),
    }

  case MODIFY_TASK_LIST_REQUEST_SUCCESS:

    taskListIndex = _.findIndex(state.taskLists, taskList => taskList['@id'] === action.taskList['@id'])
    newTaskLists.splice(taskListIndex, 1, {
      ...action.taskList,
      items: action.taskList.items,
    })

    return {
      ...state,
      taskLists: newTaskLists,
    }

  case ADD_TASK_LIST_REQUEST_SUCCESS:

    return {
      ...state,
      taskLists: Array.prototype.concat(state.taskLists, action.taskList),
    }

  case UPDATE_TASK:

    if (!acceptTask(action.task, state.date)) {
      return state
    }

    let newUnassignedTasks = state.unassignedTasks.slice(0)
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
      } else {
        newTaskLists.push(
          createTaskList(action.task.assignedTo, [ action.task ])
        )
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

  case TASK_LIST_UPDATED:

    taskListIndex = _.findIndex(state.taskLists, taskList => taskList['@id'] === action.taskList['@id'])

    if (-1 === taskListIndex) {

      return state
    }

    newTaskLists.splice(taskListIndex, 1, {
      ...state.taskLists[taskListIndex],
      distance: action.taskList.distance,
      duration: action.taskList.duration,
      polyline: action.taskList.polyline,
    })

    return {
      ...state,
      taskLists: newTaskLists,
    }

  }

  return state
}

const _taskListsLoading = (state = false, action) => {
  switch(action.type) {
  case ADD_TASK_LIST_REQUEST:
    return true
  case ADD_TASK_LIST_REQUEST_SUCCESS:
  case MODIFY_TASK_LIST_REQUEST_SUCCESS:
    return false
  case SET_TASK_LISTS_LOADING:
    return action.loading
  default:
    return state
  }
}

export default (state = initialState, action) => {

  const { unassignedTasks, taskLists, taskListsLoading } = rootReducer(state, action)

  return {
    ...state,
    unassignedTasks,
    taskLists,
    taskListsLoading: _taskListsLoading(taskListsLoading, action),
  }
}
