export { default as dateReducer } from './dateReducer'
export { default as taskEntityReducers } from './taskEntityReducers'
export { default as taskListEntityReducers } from './taskListEntityReducers'
export { default as uiReducers } from './uiReducers'

export {
  selectSelectedDate,
  selectTaskLists,
  selectAssignedTasks,
  selectUnassignedTasks,
  selectAllTasks,
  selectTasksWithColor,
} from './selectors'

export * from './actions'

import {
  mapToColor,
  tasksToIds,
  addOrReplaceTasks,
} from './taskUtils'

export const taskUtils = {
  mapToColor,
  tasksToIds,
  addOrReplaceTasks,
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
  addOrReplaceTaskList,
  addOrReplaceTaskLists,
} from './taskListEntityUtils'

export const taskListEntityUtils = {
  findTaskListByTask,
  findTaskListByUsername,
  addAssignedTask,
  removeUnassignedTask,
  addOrReplaceTaskList,
  addOrReplaceTaskLists,
}
