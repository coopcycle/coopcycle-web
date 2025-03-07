export { default as dateReducer } from './dateReducer'
export { default as taskEntityReducers } from './taskEntityReducers'
export { default as taskListEntityReducers } from './taskListEntityReducers'
export { default as uiReducers } from './uiReducers'
export { default as tourEntityReducers } from './tourEntityReducers'
export * from './adapters'

export {
  selectSelectedDate,
  selectAssignedTasks,
  selectUnassignedTasks,
  selectAllTasks,
  selectTasksWithColor,
} from './selectors'

export * from './actions'

import {
  mapToColor,
  tasksToIds,
  groupLinkedTasks,
} from './taskUtils'

export const taskUtils = {
  mapToColor,
  tasksToIds,
  groupLinkedTasks,
}

import {
  replaceTasksWithIds,
} from './taskListUtils'

export const taskListUtils = {
  replaceTasksWithIds,
}

import {
  findTaskListByTask,
  findTaskListByUsername,
  addAssignedTask,
  removeUnassignedTask,
} from './taskListEntityUtils'

export const taskListEntityUtils = {
  findTaskListByTask,
  findTaskListByUsername,
  addAssignedTask,
  removeUnassignedTask,
}
