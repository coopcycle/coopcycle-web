import _ from 'lodash';
import { createSelector } from 'reselect';
import { mapToColor } from './taskUtils';
import { assignedItemsIds } from './taskListUtils';
import { taskAdapter, taskListAdapter, tourAdapter } from './adapters'

const taskSelectors = taskAdapter.getSelectors((state) => state.logistics.entities.tasks)
export const taskListSelectors = taskListAdapter.getSelectors((state) => state.logistics.entities.taskLists)
const tourSelectors = tourAdapter.getSelectors((state) => state.logistics.entities.tours)

export const selectSelectedDate = state => state.logistics.date

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
  taskListSelectors.selectAll,
  taskLists => assignedItemsIds(taskLists)
)

export const selectUnassignedTasks = createSelector(
  selectAllTasks,
  selectAssignedTasks,
  (allTasks, assignedItemIds) =>
    _.filter(allTasks, task => assignedItemIds.findIndex(assignedItemId => task['@id'] == assignedItemId) == -1)
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