import { createSelector } from 'reselect'
import Moment from 'moment'
import { extendMoment } from 'moment-range'
import { integerToColor, groupLinkedTasks } from './utils'
import { filter, forEach, includes, intersectionWith, isEqual, mapValues } from 'lodash'

const moment = extendMoment(Moment)

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
  state => state.date,
  (tasks, filters, date) => {

    return filter(tasks.slice(0), task => {

      return selectIsVisibleTask({
        task,
        filters,
        date,
      })
    })
  }
)

export const selectBookedUsernames = createSelector(
  state => state.taskLists,
  taskLists => taskLists.map(taskList => taskList.username)
)

export const selectTasksWithColor = createSelector(
  state => state.allTasks,
  allTasks =>
    mapValues(groupLinkedTasks(allTasks), taskIds => integerToColor(taskIds.reduce((accumulator, value) => accumulator + value)))
)

export const selectIsVisibleTask = createSelector(
  state => state.task,
  state => state.filters,
  state => state.date,
  (task, filters, date) => {

    const {
      showFinishedTasks,
      showCancelledTasks,
      alwayShowUnassignedTasks,
      tags,
      hiddenCouriers,
      timeRange,
    } = filters

    const isFinished = includes(['DONE', 'FAILED'], task.status)
    const isCancelled = 'CANCELLED' === task.status

    if (alwayShowUnassignedTasks && !task.isAssigned) {
      if (!showCancelledTasks && isCancelled) {
        return false
      }
      return true
    }

    if (!showFinishedTasks && isFinished) {
      return false
    }

    if (!showCancelledTasks && isCancelled) {
      return false
    }

    if (tags.length > 0) {

      if (task.tags.length === 0) {
        return false
      }

      if (intersectionWith(task.tags, tags, (tag, slug) => tag.slug === slug).length === 0) {
        return false
      }
    }

    if (hiddenCouriers.length > 0) {

      if (!task.isAssigned) {
        return false
      }

      if (includes(hiddenCouriers, task.assignedTo)) {
        return false
      }
    }

    if (!isEqual(timeRange, [0, 24])) {

      const [ start, end ] = timeRange

      const startHour = start
      const endHour = end === 24 ? 23 : end
      const endMinute = end === 24 ? 59 : 0

      const dateAsRange = moment.range(
        moment(date).set({ hour: startHour, minute: 0 }),
        moment(date).set({ hour: endHour, minute: endMinute })
      )

      const range = moment.range(
        moment(task.doneAfter),
        moment(task.doneBefore)
      )

      if (!range.overlaps(dateAsRange)) {
        return false
      }
    }

    return true
  }
)
