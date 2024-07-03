import { createSelector } from 'reselect'
import Fuse from 'fuse.js'
import Holidays from 'date-holidays'
import { rrulestr } from 'rrule'
import {
  createEntityAdapter,
} from '@reduxjs/toolkit'

import { moment } from '../../coopcycle-frontend-js'
import { selectUnassignedTasks, selectAllTasks, selectSelectedDate, taskListAdapter, taskAdapter, tourAdapter } from '../../coopcycle-frontend-js/logistics/redux'
import { filter, forEach, find, reduce, map, differenceWith, includes, mapValues } from 'lodash'
import { isTaskVisible, isOffline, recurrenceTemplateToArray } from './utils'
import { taskUtils } from '../../coopcycle-frontend-js/logistics/redux';
import { selectAllTours, selectTaskIdToTourIdMap } from '../../../shared/src/logistics/redux/selectors'

const taskListSelectors = taskListAdapter.getSelectors((state) => state.logistics.entities.taskLists)
export const taskSelectors = taskAdapter.getSelectors((state) => state.logistics.entities.tasks)
export const tourSelectors = tourAdapter.getSelectors((state) => state.logistics.entities.tours)

export const recurrenceRulesAdapter = createEntityAdapter({
  selectId: (o) => o['@id'],
  sortComparer: (a, b) => a.orgName.localeCompare(b.orgName),
})

// UI selectors
export const selectCurrentTask = state => state.logistics.ui.currentTask
export const selectIsTourDragging = state => state.logistics.ui.isTourDragging
export const selectExpandedTourPanelsIds = state => state.logistics.ui.expandedTourPanelsIds
export const selectExpandedTaskListPanelsIds = state => state.logistics.ui.expandedTaskListPanelsIds
export const selectExpandedTasksGroupsPanelsIds = state => state.logistics.ui.expandedTasksGroupPanelIds
export const selectTaskToShow = state => state.logistics.ui.taskToShow
export const selectLoadingTourPanelsIds = state => state.logistics.ui.loadingTourPanelsIds
export const selectTaskListsLoading = state => state.logistics.ui.taskListsLoading
export const selectUnassignedTasksLoading = state => state.logistics.ui.unassignedTasksLoading
export const selectOrderOfUnassignedTasks = state => state.logistics.ui.unassignedTasksIdsOrder
export const selectOrderOfUnassignedToursAndGroups = state => state.logistics.ui.unassignedToursOrGroupsOrderIds

// Settings selectors
export const selectSettings = state => state.settings
export const selectFiltersSetting = state => state.settings.filters
export const selectHiddenCouriersSetting = state => state.settings.filters.hiddenCouriers
export const selectAreToursEnabled = state => state.settings.toursEnabled
export const selectIsRecurrenceRulesVisible = state => state.settings.isRecurrenceRulesVisible
export const selectTaskListGroupMode = state => state.taskListGroupMode
export const selectSplitDirection = state => state.rightPanelSplitDirection
export const selectPolylineEnabledByUsername = username => state => state.polylineEnabled[username]
export const selectTourPolylinesEnabledById = tourId => state => state.tourPolylinesEnabled[tourId]
export const selectAllTags = state => state.config.tags

export const getProductNameById = id => store => {
  return store.dashboard.dashboards.filter(({ Id }) => Id === id)[0]
    .Name;
}

export const selectCouriers = state => state.config.couriersList
export const selectTaskEvents = state => state.taskEvents

export const selectTaskLists = taskListSelectors.selectAll

export const selectBookedUsernames = taskListSelectors.selectIds


export const belongsToGroup = task => Object.prototype.hasOwnProperty.call(task, 'group') && task.group
export const belongsToTour = task => state => selectTaskIdToTourIdMap(state).has(task['@id'])


export const selectGroups = createSelector(
  selectUnassignedTasks,
  state => state.taskListGroupMode,
  selectTaskIdToTourIdMap,
  (unassignedTasks, taskListGroupMode, taskIdToTourIdMap) => {

    if (taskListGroupMode !== 'GROUP_MODE_FOLDERS') {
      return []
    }

    const groupsMap = new Map()
    const groups = []

    const tasksWithGroup = filter(
      unassignedTasks,
      task => belongsToGroup(task) && !taskIdToTourIdMap.has(task['@id']) // if the task is in a tour we don't want it to be displayed in "Unassigned > Group"
    )

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
  selectTaskIdToTourIdMap,
  (unassignedTasks, taskListGroupMode, taskIdToTourIdMap) => {

    let standaloneTasks = unassignedTasks

    if (taskListGroupMode === 'GROUP_MODE_FOLDERS') {
      standaloneTasks = filter(unassignedTasks, task => !belongsToGroup(task))
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
        if (moment(a.before).isSame(b.before) && a.type === 'PICKUP') {
          return -1
        } else {
          // put on top of the list the tasks that have an end of delivery window that finishes sooner
          return moment(a.before).isBefore(b.before) ? -1 : 1
        }
      })
    }

    return filter(standaloneTasks, t => !taskIdToTourIdMap.has(t['@id']))
  }
)

export const selectVisibleTaskIds = createSelector(
  selectAllTasks,
  selectFiltersSetting,
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
  tourSelectors.selectEntities,
  (tasksById, taskListsByUsername, allTours) => {
    const asTheCrowFliesTaskLists = mapValues(taskListsByUsername, taskList => {
      const polyline = map(taskList.items, itemId => {

        const item = tasksById[itemId]

        return item ? [
          item.address.geo.latitude,
          item.address.geo.longitude
        ] : []
      })

      return filter(polyline, (coords) => coords.length === 2)
    })

    const asTheCrowFliesTours = mapValues(allTours, tour => {
      const polyline = map(tour.items, itemId => {
        const item = tasksById[itemId]

        return item ? [
          item.address.geo.latitude,
          item.address.geo.longitude
        ] : []
      })

      return filter(polyline, (coords) => coords.length === 2)
    })

    return Object.assign({}, asTheCrowFliesTaskLists, asTheCrowFliesTours)
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
  threshold: 0.4,
  minMatchCharLength: 3,
  ignoreLocation: true,
  keys: ['id', 'metadata.order_number', 'tags.name', 'tags.slug', 'address.contactName', 'address.name','address.streetAddress','comments', 'orgName']
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
  // FIXME
  // We use filter, to filter out "undefined" objects
  // Best would be to clear the selectedTasks after Redux action completes
  (tasksById, selectedTasks) => filter(selectedTasks.map(id => tasksById[id]))
)

export const selectVisiblePickupTasks = createSelector(
  taskSelectors.selectAll,
  selectHiddenTaskIds,
  (tasks, hiddenTaskIds) => filter(tasks, task => task.type === 'PICKUP' && !hiddenTaskIds.includes(task['@id']))
)

export const selectRestaurantAddressIds = state => state.config.pickupClusterAddresses

export const selectLinkedTasksIds = createSelector(
  taskSelectors.selectAll,
  (tasks) => {
    const groups = taskUtils.groupLinkedTasks(tasks)
    return Object.keys(groups)
  }
)

export const selectTagsSelectOptions = createSelector(
  selectAllTags,
  (allTags) => allTags.map((tag) => {return {...tag, isTag: true, label: tag.name, value: tag.slug}})
)

const tourColorPalette = ["#556b2f","#8b4513","#8b0000","#808000","#483d8b","#008000","#3cb371","#bc8f8f","#b8860b","#4682b4","#d2691e","#9acd32","#20b2aa","#00008b","#32cd32","#8b008b","#d2b48c","#9932cc","#ff0000","#ff8c00","#ffd700","#6a5acd","#c71585","#0000cd","#00ff00","#00fa9a","#dc143c","#00ffff","#f4a460","#0000ff","#a020f0","#adff2f","#ff6347","#da70d6","#ff00ff","#db7093","#f0e68c","#fa8072","#ffff54","#6495ed","#dda0dd","#90ee90","#87cefa","#ff69b4"]

export const selectTourIdToColorMap = createSelector(
  selectAllTours,
  (allTours) => {
    let toColorMap = new Map()
    allTours.forEach((tour, index) => {
        toColorMap.set(tour["@id"], tourColorPalette[index % tourColorPalette.length])
  })
  return toColorMap
})
