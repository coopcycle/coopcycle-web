import _ from 'lodash';
import { createSelector } from 'reselect';
import { mapToColor } from './taskUtils';
import { assignedTasks } from './taskListUtils';
import { taskAdapter, taskListAdapter, tourAdapter } from './adapters'

const taskSelectors = taskAdapter.getSelectors((state) => state.logistics.entities.tasks)
export const taskListSelectors = taskListAdapter.getSelectors((state) => state.logistics.entities.taskLists)
const tourSelectors = tourAdapter.getSelectors((state) => state.logistics.entities.tours)

export const selectSelectedDate = state => state.logistics.date

// FIXME
// This is not optimized
// Each time any task is updated, the tasks lists are looped over
// Also, it generates copies all the time
// Replace this with a selectTaskListItemsByUsername selector, used by the <TaskList> component
// https://redux.js.org/tutorials/essentials/part-6-performance-normalization#memoizing-selector-functions
export const selectTasksListsWithItems = createSelector(
  taskListSelectors.selectEntities,
  taskSelectors.selectEntities,
  (taskListsById, tasksById) =>
    Object.values(taskListsById).map(taskList => {
      let newTaskList = {...taskList}
      delete newTaskList.items

      newTaskList.items = taskList.items
        .filter(taskId => Object.prototype.hasOwnProperty.call(tasksById, taskId)) // a task with this id may be not loaded yet
        .map(taskId => tasksById[taskId])

      return newTaskList
    })
)

export const selectAllTasks = taskSelectors.selectAll

const selectTaskId = (state, taskId) => taskId

export const selectTaskById = createSelector(selectAllTasks, selectTaskId,
  (tasks, taskId) => tasks.find(t => t['@id'] === taskId)
)

const selectTasksId = (state, tasksId) => tasksId

export const selectTasksById = createSelector(
  selectAllTasks,
  selectTasksId,
  (allTasks, tasksId) => tasksId.map(taskId => allTasks.find(t => t['@id'] === taskId))
)

export const selectAssignedTasks = createSelector(
  selectTasksListsWithItems,
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

export const selectTaskListByUsername = (state, props) => taskListSelectors.selectById(state, props.username)

export const selectTaskListTasksByUsername = createSelector(
  selectTaskListByUsername,
  selectAllTasks,
  tourSelectors.selectAll,
  (taskList, allTasks, allTours) => {
    return taskList.items.reduce((acc, it) => {
      if (it.startsWith('/api/tours')) {
        const tour = allTours.find(t => t['@id'] === it)
        acc = [...acc, ...tour.items.map(tId => allTasks.find(t => t['@id'] === tId))]
      } else {
        acc.push(allTasks.find(t => t["@id"] === it))
      }
      return acc
    }, [])
  }

)


// https://github.com/reduxjs/reselect#connecting-a-selector-to-the-redux-store
// https://redux.js.org/recipes/computing-derived-data
export const makeSelectTaskListItemsByUsername = () => {

  return createSelector(
    selectTaskListByUsername,
    selectTaskIdToTourIdMap,
    selectAllTours,
    (tasks, taskList, taskIdToTourIdMap, allTours) => {

      if (!taskList) {
        return []
      }

      return taskList.items
        .filter(id => Object.prototype.hasOwnProperty.call(tasks, id)) // a task with this id may be not loaded yet
        .map(id => tasks[id])
        .reduce((taskListItems, task, position) => {

          if (taskIdToTourIdMap.has(task['@id'])) {
            const tourId = taskIdToTourIdMap.get(task['@id'])
            let tourIndex = _.findIndex(taskListItems, item => item['@id'] === tourId)

            if (tourIndex === -1) {
              const tour = allTours.find(t => t['@id'] === tourId)
              taskListItems.push(tour)
              tourIndex = taskListItems.length - 1
            }

            // update tour items with the task position in the tasklist, because we will need it later...
            // we assume that the tasks are in the order corresponding to their position in taskList.items and tour.items
            // see
            let taskIndex = taskListItems[tourIndex].items.findIndex(t => t['@id'] === task['@id'])
            taskListItems[tourIndex].items[taskIndex] = {...task, position: position}
          } else {
            taskListItems.push({...task, position})
          }

          return taskListItems

        }, [])
    }
  )
}

export const selectAllTours = createSelector(
  tourSelectors.selectAll,
  (allTours) => allTours
)

const selectItemId = (state, itemId) => itemId


export const selectItemAssignedTo = createSelector(
  taskListSelectors.selectAll,
  selectItemId,
  (allTaskLists, itemId) => { // item can be a task or a tour (!)
    const tl = allTaskLists.find(tl => tl.items.includes(itemId))
    if (tl) {
      return tl.username
    }
  }
)

const selectTourId = (state, tourId) => tourId

export const selectTourById = createSelector(selectAllTours, selectTourId,
  (tours, tourId) => tours.find(t => t['@id'] === tourId)
)

export const selectUnassignedTours = createSelector(
  selectAllTours,
  taskListSelectors.selectAll,
  (allTours, allTaskLists) => {
    let unassignedTours = []
    _.map(allTours, tour => {
      const tl = allTaskLists.find(tl => tl.items.includes(tour['@id']))
      if (!tl) {
        unassignedTours.push(tour)
      }
    })
    return unassignedTours
  }
)

export const selectTaskIdToTourIdMap = createSelector(
  selectAllTours,
  (allTours) => {
    let taskIdToTourIdMap = new Map()
    allTours.forEach((tour) => {
      tour.items.forEach(taskId => {
        taskIdToTourIdMap.set(taskId, tour['@id'])
    })
  })
  return taskIdToTourIdMap
})