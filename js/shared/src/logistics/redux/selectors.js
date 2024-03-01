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

const belongsToTour = task => Object.prototype.hasOwnProperty.call(task, 'tour') && task.tour

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
        .reduce((items, task, position) => {

          if (belongsToTour(task)) {

            const tourIndex = _.findIndex(items, item => {

              return belongsToTour(task)
                && item['@type'] === 'Tour' && task.tour['@id'] === item['@id']
            })

            if (-1 === tourIndex) {
              items.push({
                ...task.tour,
                '@type': 'Tour',
                items: [
                  {...task, position}
                ]
              })
            } else {
              const tour = items[tourIndex]
              tour.items.push({...task, position})
            }

          } else {
            items.push({...task, position})
          }

          return items

        }, [])
    }
  )
}

// FIXME This is recalculated all the time we change a tasks
export const selectTasksWithTour = createSelector(selectAllTasks,
  (allTasks) => {
    return allTasks.filter(t => t.tour)
})

// FIXME This is recalculated all the time we change a task
export const selectAllTours = createSelector(
  tourSelectors.selectAll,
  selectTasksWithTour,
  (allTours, tasksWithTour) => {
    const toursWithItems = []
    forEach(allTours, unassignedTour => {
      let items = [];
      forEach(unassignedTour.itemIds, itemId => {
        let task = tasksWithTour.find(task => task['@id'] == itemId)
        items.push(task)
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
