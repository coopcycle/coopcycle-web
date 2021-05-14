import _ from 'lodash';
import { createSelector } from 'reselect';
import { mapToColor } from './taskUtils';
import { assignedTasks } from './taskListUtils';
import { taskAdapter, taskListAdapter } from './adapters'

const taskSelectors = taskAdapter.getSelectors((state) => state.logistics.entities.tasks)
const taskListSelectors = taskListAdapter.getSelectors((state) => state.logistics.entities.taskLists)

export const selectSelectedDate = state => state.logistics.date

// FIXME
// This is not optimized
// Each time any task is updated, the tasks lists are looped over
// Also, it generates copies all the time
// Replace this with a selectTaskListItemsByUsername selector, used by the <TaskList> component
// https://redux.js.org/tutorials/essentials/part-6-performance-normalization#memoizing-selector-functions
export const selectTaskLists = createSelector(
  taskListSelectors.selectEntities,
  taskSelectors.selectEntities,
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

export const selectAllTasks = taskSelectors.selectAll

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

const selectTaskListByUsername = (state, props) =>
  taskListSelectors.selectById(state, props.username)

// https://github.com/reduxjs/reselect#connecting-a-selector-to-the-redux-store
// https://redux.js.org/recipes/computing-derived-data
export const makeSelectTaskListItemsByUsername = () => {

  return createSelector(
    taskSelectors.selectEntities, // FIXME This is recalculated all the time
    selectTaskListByUsername,
    (tasks, taskList) => {

      if (!taskList) {
        return []
      }

      return taskList.itemIds
        .filter(id => Object.prototype.hasOwnProperty.call(tasks, id)) // a task with this id may be not loaded yet
        .map(id => tasks[id])
    }
  )
}
