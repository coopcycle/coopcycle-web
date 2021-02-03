import { createSelector } from 'reselect'
import { moment } from '../../coopcycle-frontend-js'
import { selectTaskLists as selectTaskListsBase, selectUnassignedTasks } from '../../coopcycle-frontend-js/dispatch/redux'
import { filter, includes, intersectionWith, isEqual, orderBy, forEach, find, reduce } from 'lodash'

export const selectTaskLists = createSelector(
  selectTaskListsBase,
  taskLists => orderBy(taskLists, 'username')
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
  selectTaskLists,
  taskLists => taskLists.map(taskList => taskList.username)
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

export const selectGroups = createSelector(
  selectUnassignedTasks,
  state => state.taskListGroupMode,
  (unassignedTasks, taskListGroupMode) => {

    if (taskListGroupMode !== 'GROUP_MODE_FOLDERS') {
      return []
    }

    const groupsMap = new Map()
    const groups = []

    const tasksWithGroup = filter(unassignedTasks, task => Object.prototype.hasOwnProperty.call(task, 'group') && task.group)

    forEach(tasksWithGroup, task => {
      const keys = Array.from(groupsMap.keys())
      const group = find(keys, group => group.id === task.group.id)
      if (!group) {
        groupsMap.set(task.group, [ task ])
      } else {
        groupsMap.get(group).push(task)
      }
    })

    groupsMap.forEach((tasks, group) => {
      groups.push({
        ...group,
        tasks
      })
    })

    return groups
  }
)

export const selectStandaloneTasks = createSelector(
  selectUnassignedTasks,
  state => state.taskListGroupMode,
  (unassignedTasks, taskListGroupMode) => {

    let standaloneTasks = unassignedTasks

    if (taskListGroupMode === 'GROUP_MODE_FOLDERS') {
      standaloneTasks = filter(unassignedTasks, task => !Object.prototype.hasOwnProperty.call(task, 'group') || !task.group)
    }

    // Order by dropoff desc, with pickup before
    if (taskListGroupMode === 'GROUP_MODE_DROPOFF_DESC') {

      const dropoffTasks = filter(standaloneTasks, t => t.type === 'DROPOFF')

      dropoffTasks.sort((a, b) => {
        return moment(a.doneBefore).isBefore(b.doneBefore) ? -1 : 1
      })

      const grouped = reduce(dropoffTasks, (acc, task) => {
        if (task.previous) {
          const prev = find(standaloneTasks, t => t['@id'] === task.previous)
          if (prev) {
            acc.push(prev)
          }
        }
        acc.push(task)

        return acc
      }, [])

      standaloneTasks = grouped
    } else {
      standaloneTasks.sort((a, b) => {
        return moment(a.doneBefore).isBefore(b.doneBefore) ? -1 : 1
      })
    }

    return standaloneTasks
  }
)
