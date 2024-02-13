import _ from 'lodash'
import { moment } from '../../coopcycle-frontend-js';
import { taskUtils } from '../../coopcycle-frontend-js/logistics/redux';

export function taskComparator(a, b) {
  return a['@id'] === b['@id']
}

export function withoutTasks(state, tasks) {

  return _.differenceWith(
    state,
    _.intersectionWith(state, tasks, taskComparator),
    taskComparator
  )
}

export function withLinkedTasks(tasks, allTasks, unique = false) {
  /*
    Pass the "unique" flag to avoid duplicates tasks in the returned array
  */

  if (!Array.isArray(tasks)) {
    tasks = [ tasks ]
  }

  const groups = taskUtils.groupLinkedTasks(allTasks)
  const newTasks = []

  tasks.forEach(task => {
    if (Object.prototype.hasOwnProperty.call(groups, task['@id']) ) {
      groups[task['@id']].forEach(taskId => {
        if (unique &&
          taskId != task['@id'] &&
          (_.find(tasks, t => t['@id'] === taskId) || // no need to push this linked task, it was in the original tasks list
          _.find(newTasks, t => t['@id'] === taskId) // no need to push this linked task, it was already found thanks to an other linked task of the same group
          )) {
          return
        } else {
          const t = _.find(allTasks, t => t['@id'] === taskId)
          newTasks.push(t)
        }
      })
    } else {
      // task with no linked tasks
      newTasks.push(task)
    }
  })

 return newTasks
}

export const timeframeToPercentage = (timeframe, reference) => {

  const after = moment(timeframe[0])
  const before = moment(timeframe[1])

  const start = moment(reference).set({ hour: 0, minute: 0, second: 0 })
  const end = moment(reference).set({ hour: 23, minute: 59, second: 59 })

  const afterAsSeconds = after.diff(start, 'seconds')
  const beforeAsSeconds = before.diff(start, 'seconds')

  const percentAfter = after.isAfter(start) ? afterAsSeconds / 86400 : 0.0
  const percentBefore = before.isBefore(end) ? (beforeAsSeconds / 86400) : 1.0

  return [ percentAfter, percentBefore ]
}

export const nowToPercentage = (now) => {

  now = now || moment()

  const start = moment(now).set({ hour: 0, minute: 0, second: 0 })
  const nowAsSeconds = moment(now).diff(start, 'seconds')

  return nowAsSeconds / 86400
}

export const isTaskVisible = (task, filters, date) => {

  const {
    showFinishedTasks,
    showCancelledTasks,
    showIncidentReportedTasks,
    alwayShowUnassignedTasks,
    tags,
    hiddenCouriers,
    timeRange,
    onlyFilter,
  } = filters

  const isFinished = _.includes(['DONE', 'FAILED'], task.status)
  const isCancelled = 'CANCELLED' === task.status
  const isIncidentReported = task.hasIncidents

  if (onlyFilter !== null) {
    switch (onlyFilter) {
      case 'showCancelledTasks':
        return isCancelled
      case 'showIncidentReportedTasks':
        return isIncidentReported
      default:
        return false
    }
  }

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

  if (!showIncidentReportedTasks && isIncidentReported) {
    return false
  }

  if (tags.length > 0) {

    if (task.tags.length === 0) {
      return false
    }

    if (_.intersectionWith(task.tags, tags, (tag, slug) => tag.slug === slug).length === 0) {
      return false
    }
  }

  if (hiddenCouriers.length > 0) {

    if (!task.isAssigned) {
      return false
    }

    if (_.includes(hiddenCouriers, task.assignedTo)) {
      return false
    }
  }

  if (!_.isEqual(timeRange, [0, 24])) {

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

// If the user has not been seen for 5min, it is considered offline
const OFFLINE_TIMEOUT = (5 * 60 * 1000)

export const isOffline = (lastSeen) => {
  const diff = moment().diff(lastSeen)
  return diff > OFFLINE_TIMEOUT
}

export const recurrenceTemplateToArray =
  template => template['@type'] === 'hydra:Collection' ? template['hydra:member'] : [ template ]

export const isInDateRange = (task, date) => {

  const dateAsRange = moment.range(
    moment(date).set({ hour:  0, minute:  0, second:  0 }),
    moment(date).set({ hour: 23, minute: 59, second: 59 })
  )

  const range = moment.range(
    moment(task.doneAfter),
    moment(task.doneBefore)
  )

  return range.overlaps(dateAsRange)
}
