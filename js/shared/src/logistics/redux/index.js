export { default as dateReducer } from './dateReducer'
export { default as taskEntityReducers } from './taskEntityReducers'
export { default as taskListEntityReducers } from './taskListEntityReducers'
export { default as uiReducers } from './uiReducers'
export * from './adapters'

export {
  selectSelectedDate,
  selectTaskLists,
  selectAssignedTasks,
  selectUnassignedTasks,
  selectAllTasks,
  selectTasksWithColor,
  makeSelectTaskListItemsByUsername,
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
  assignedTasks,
} from './taskListUtils'

export const taskListUtils = {
  replaceTasksWithIds,
  assignedTasks,
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
