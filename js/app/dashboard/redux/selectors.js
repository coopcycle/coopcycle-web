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

    let tasksFiltered = tasks.slice(0)

    const {
      showFinishedTasks,
      showCancelledTasks,
      tags,
      hiddenCouriers,
      timeRange,
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

    if (!isEqual(timeRange, [0, 24])) {

      tasksFiltered =
        filter(tasksFiltered, task => {

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

          return range.overlaps(dateAsRange)
        })
    }

    return tasksFiltered
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
