import _ from 'lodash';
import { createSelector } from 'reselect';
import { mapToColor } from './taskUtils';
import { assignedTasks } from './taskListUtils';

export const selectSelectedDate = state => state.logistics.date

export const selectTaskLists = createSelector(
  state => state.logistics.entities.taskLists.byId,
  state => state.logistics.entities.tasks.byId,
  (taskListsById, tasksById) =>
    Object.values(taskListsById).map(taskList => {
      let newTaskList = {...taskList}
      delete newTaskList.itemIds

      newTaskList.items = taskList.itemIds
        .filter(taskId => Object.prototype.hasOwnProperty.call(tasksById, taskId)) // a task with this id may be not loaded yet
        .map(taskId => tasksById[taskId])

      return newTaskList
    })
)

export const selectAllTasks = createSelector(
  state => state.logistics.entities.tasks.byId,
  tasksById => Object.values(tasksById)
)

export const selectAssignedTasks = createSelector(
  selectTaskLists,
  taskLists => assignedTasks(taskLists)
)

export const selectUnassignedTasks = createSelector(
  selectAllTasks,
  selectAssignedTasks,
  (allTasks, assignedTasks) =>
    _.filter(allTasks, task => assignedTasks.findIndex(assignedTask => task['@id'] == assignedTask['@id']) == -1)
)

export const selectTasksWithColor = createSelector(
   selectAllTasks,
  allTasks => mapToColor(allTasks)
)
