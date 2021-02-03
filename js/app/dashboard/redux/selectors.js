import { createSelector } from 'reselect'
import Fuse from 'fuse.js'
import { moment } from '../../coopcycle-frontend-js'
import { selectTaskLists as selectTaskListsBase, selectUnassignedTasks, selectAllTasks } from '../../coopcycle-frontend-js/dispatch/redux'
import { filter, orderBy, forEach, find, reduce, map, differenceWith } from 'lodash'
import { isTaskVisible } from './utils'

export const selectTaskLists = createSelector(
  selectTaskListsBase,
  taskLists => orderBy(taskLists, 'username')
)

export const selectBookedUsernames = createSelector(
  selectTaskLists,
  taskLists => taskLists.map(taskList => taskList.username)
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

export const selectVisibleTaskIds = createSelector(
  selectAllTasks,
  state => state.filters,
  state => state.date,
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
  selectTaskLists,
  (taskLists) => {
    let asTheCrowFlies = {}
    forEach(taskLists, taskList => {
      asTheCrowFlies[taskList.username] =
        map(taskList.items, item => ([ item.address.geo.latitude, item.address.geo.longitude ]))
    })
    return asTheCrowFlies
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
