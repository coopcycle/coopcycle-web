import _, { forEach } from 'lodash';
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
      delete newTaskList.itemIds

      newTaskList.items = taskList.itemIds
        .filter(taskId => Object.prototype.hasOwnProperty.call(tasksById, taskId)) // a task with this id may be not loaded yet
        .map(taskId => tasksById[taskId])

      return newTaskList
    })
)

export const selectAllTasks = taskSelectors.selectAll

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

const selectTaskListByUsername = (state, props) => taskListSelectors.selectById(state, props.username)


// https://github.com/reduxjs/reselect#connecting-a-selector-to-the-redux-store
// https://redux.js.org/recipes/computing-derived-data
export const makeSelectTaskListItemsByUsername = () => {

  return createSelector(
    taskSelectors.selectEntities, // FIXME This is recalculated all the time
    selectTaskListByUsername,
    selectTaskIdToTourIdMap,
    selectAllTours,
    (tasks, taskList, taskIdToTourIdMap, allTours) => {

      if (!taskList) {
        return []
      }

      return taskList.itemIds
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
            // we assume that the tasks are in the order corresponding to their position in taskList.itemIds and tour.itemIds
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

// FIXME This is recalculated all the time we change a task
export const selectAllTours = createSelector(
  tourSelectors.selectAll,
  selectAllTasks,
  (allTours, allTasks) => {
    const toursWithItems = []
    forEach(allTours, unassignedTour => {
      let items = [];
      forEach(unassignedTour.itemIds, itemId => {
        let task = allTasks.find(task => task['@id'] == itemId)
        if (task) {
          items.push(task)
        } else {
          console.error('unable to find task for tour')
        }

      })
      toursWithItems.push({
        ...unassignedTour,
        items,
      })
    })
    return toursWithItems
  }
)

const selectTourId = (state, tourId) => tourId

export const selectTourById = createSelector(selectAllTours, selectTourId,
  (tours, tourId) => tours.find(t => t['@id'] === tourId)
)

export const isTourAssigned = (tour) => tour.items.length > 0 ? _.every(tour.items, (item) => item.isAssigned) : false
export const isTourUnassigned = (tour) => {
  if (tour.items.length === 0) return true
  else return tour.items.length > 0 ? _.every(tour.items, (item) => !item.isAssigned) : false
}
export const tourIsAssignedTo = (tour) => tour.items.length > 0 ? tour.items[0].assignedTo : undefined

// FIXME This is recalculated all the time we change a task
export const selectUnassignedTours = createSelector(
  selectAllTours,
  (allTours) => _.filter(allTours, t => isTourUnassigned(t))
)

export const selectTaskIdToTourIdMap = createSelector(
  selectAllTours,
  (allTours) => {
    let taskIdToTourIdMap = new Map()
    allTours.forEach((tour) => {
      tour.itemIds.forEach(taskId => {
        taskIdToTourIdMap.set(taskId, tour['@id'])
    })
  })
  return taskIdToTourIdMap
})