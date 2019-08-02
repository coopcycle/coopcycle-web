import { createSelector } from 'reselect'
import { differenceWith, filter, forEach, includes, intersectionWith } from 'lodash'

function flattenTaskLists(taskLists) {
  const tasks = []
  forEach(taskLists, taskList => taskList.items.forEach(task => tasks.push(task)))

  return tasks
}

export const selectTasks = createSelector(
  state => state.unassignedTasks,
  state => flattenTaskLists(state.taskLists),
  (unassignedTasks, assignedTasks) => {
    return unassignedTasks.slice(0).concat(assignedTasks)
  }
)

export const selectFilteredTasks = createSelector(
  state => state.tasks,
  state => state.filters,
  (tasks, filters) => {

    let tasksFiltered = tasks.slice(0)

    const {
      showFinishedTasks,
      showCancelledTasks,
      tags,
      hiddenCouriers,
    } = filters

    if (!showFinishedTasks) {
      tasksFiltered =
        filter(tasksFiltered, task => !includes(['DONE', 'FAILED'], task.status))
    }

    if (!showCancelledTasks) {
      tasksFiltered =
        filter(tasksFiltered, task => 'CANCELLED' !== task.status)
    }

    if (tags.length > 0) {

      tasksFiltered =
        filter(tasksFiltered, task => {
          if (task.tags.length === 0) {
            return false
          }

          return intersectionWith(task.tags, tags, (tag, slug) => tag.slug === slug).length > 0
        })
    }

    if (hiddenCouriers.length > 0) {

      tasksFiltered =
        filter(tasksFiltered, task => {
          if (!task.isAssigned) {
            return false
          }

          return !includes(hiddenCouriers, task.assignedTo)
        })
    }

    return tasksFiltered
  }
)

export const selectBookedUsernames = createSelector(
  state => state.taskLists,
  taskLists => taskLists.map(taskList => taskList.username)
)
