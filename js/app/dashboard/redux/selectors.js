import { createSelector } from 'reselect'
import Fuse from 'fuse.js'
import Holidays from 'date-holidays'
import { rrulestr } from 'rrule'
import {
  createEntityAdapter,
} from '@reduxjs/toolkit'

import { moment } from '../../coopcycle-frontend-js'
import { selectUnassignedTasks, selectAllTasks, selectSelectedDate, taskListAdapter, taskAdapter } from '../../coopcycle-frontend-js/logistics/redux'
import { filter, forEach, find, reduce, map, differenceWith, includes, mapValues } from 'lodash'
import { isTaskVisible, isOffline, recurrenceTemplateToArray } from './utils'

const taskListSelectors = taskListAdapter.getSelectors((state) => state.logistics.entities.taskLists)
const taskSelectors = taskAdapter.getSelectors((state) => state.logistics.entities.tasks)

export const recurrenceRulesAdapter = createEntityAdapter({
  selectId: (o) => o['@id'],
  sortComparer: (a, b) => a.orgName.localeCompare(b.orgName),
})

export const selectCurrentTask = state => state.logistics.ui.currentTask
export const selectCouriers = state => state.config.couriersList
export const selectTaskEvents = state => state.taskEvents

export const selectTaskLists = taskListSelectors.selectAll

export const selectBookedUsernames = taskListSelectors.selectIds

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
    if (taskListGroupMode === 'GROUP_MODE_DROPOFF_DESC' || taskListGroupMode === 'GROUP_MODE_DROPOFF_ASC') {

      const dropoffTasks = filter(standaloneTasks, t => t.type === 'DROPOFF')

      dropoffTasks.sort((a, b) => {
        return moment(a.before).isBefore(b.before) ?
          (taskListGroupMode === 'GROUP_MODE_DROPOFF_DESC' ? -1 : 1)
          :
          (taskListGroupMode === 'GROUP_MODE_DROPOFF_DESC' ? 1 : -1)
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
        return moment(a.before).isBefore(b.before) ? -1 : 1
      })
    }

    return standaloneTasks
  }
)

export const selectVisibleTaskIds = createSelector(
  selectAllTasks,
  state => state.settings.filters,
  selectSelectedDate,
  (tasks, filters, date) => filter(tasks, task => isTaskVisible(task, filters, date)).map(task => task['@id'])
)

export const selectPolylines = createSelector(
  selectTaskLists,
  (taskLists) => {
    let polylines = {}
    forEach(taskLists, taskList => {
      polylines[taskList.username] = taskList.polyline
    })
    return polylines
  }
)

export const selectAsTheCrowFlies = createSelector(
  taskSelectors.selectEntities,
  taskListSelectors.selectEntities,
  (tasksById, taskListsByUsername) => {

    return mapValues(taskListsByUsername, taskList => {
      const polyline = map(taskList.itemIds, itemId => {
        const item = tasksById[itemId]

        return item ? [
          item.address.geo.latitude,
          item.address.geo.longitude
        ] : []
      })

      return filter(polyline, (coords) => coords.length === 2)
    })
  }
)

export const selectHiddenTaskIds = createSelector(
  selectAllTasks,
  selectVisibleTaskIds,
  (tasks, visibleTaskIds) => {
    const taskIds = tasks.map(task => task['@id'])
    return differenceWith(taskIds, visibleTaskIds)
  }
)

const fuseOptions = {
  shouldSort: true,
  includeScore: true,
  keys: [{
    name: 'id',
    weight: 0.7
  }, {
    name: 'tags.slug',
    weight: 0.1
  }, {
    name: 'address.name',
    weight: 0.1
  }, {
    name: 'address.streetAddress',
    weight: 0.1
  }]
}

export const selectFuseSearch = createSelector(
  selectAllTasks,
  (tasks) => new Fuse(tasks, fuseOptions)
)

export const selectPositions = createSelector(
  state => state.tracking.positions,
  state => state.tracking.offline,
  (positions, offline) => positions.map(position => ({
    ...position,
    offline: includes(offline, position.username) ? true : isOffline(position.lastSeen),
  }))
)

export const selectCountry = createSelector(
  selectSelectedDate,
  () => $('body').data('country')
)

export const selectNextWorkingDay = createSelector(
  selectCountry,
  selectSelectedDate,
  (country, date) => {

    const holidays = new Holidays(country.toUpperCase())

    let cursor = moment(date).startOf('day')
    do {
      cursor = cursor.add(1, 'day')
    } while (holidays.isHoliday(cursor.toDate()))

    return cursor.format()
  }
)

const recurrenceRulesSelectors = recurrenceRulesAdapter.getSelectors(
  (state) => state.rrules
)

export const selectRecurrenceRules = createSelector(
  selectSelectedDate,
  recurrenceRulesSelectors.selectAll,
  (date, rrules) => {

    const startOfDayUTC = moment.utc(`${moment(date).format('YYYY-MM-DD')} 00:00:00`).toDate()
    const endOfDayUTC   = moment.utc(`${moment(date).format('YYYY-MM-DD')} 23:59:59`).toDate()

    return filter(rrules, rrule => {

      const tasks = recurrenceTemplateToArray(rrule.template)

      const matchingTasks = filter(tasks, task => {
        const ruleObj = rrulestr(rrule.rule, {
          dtstart: moment.utc(`${moment(date).format('YYYY-MM-DD')} ${task.after}`).toDate()
        })

        return ruleObj.between(startOfDayUTC, endOfDayUTC, true).length > 0
      })

      return matchingTasks.length > 0
    })
  }
)

export const selectCouriersWithExclude = createSelector(
  selectCouriers,
  taskListSelectors.selectAll,
  (state, exclude) => exclude,
  (couriers, taskLists, exclude) => {

    if (exclude) {
      const usernames = taskLists.map(taskList => taskList.username)

      return filter(couriers, courier => !includes(usernames, courier.username))
    }

    return couriers
  }
)

export const selectCurrentTaskEvents = createSelector(
  selectCurrentTask,
  selectTaskEvents,
  (currentTask, taskEvents) => {

    if (!currentTask) {
      return []
    }

    return Object.prototype.hasOwnProperty.call(taskEvents, currentTask['@id']) ? taskEvents[currentTask['@id']] : []
  }
)

export const selectSelectedTasks = createSelector(
  taskSelectors.selectEntities,
  state => state.selectedTasks,
  (tasksById, selectedTasks) => selectedTasks.map(id => tasksById[id])
)

export const selectVisiblePickupTasks = createSelector(
  taskSelectors.selectAll,
  selectHiddenTaskIds,
  (tasks, hiddenTaskIds) => filter(tasks, task => task.type === 'PICKUP' && !hiddenTaskIds.includes(task['@id']))
)

export const selectRestaurantAddressIds = state => state.config.pickupClusterAddresses
