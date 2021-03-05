import _ from 'lodash'
import moment from 'moment'

export function createTaskList(username, items = []) {

  return {
    '@context': '/api/contexts/TaskList',
    '@id': null,
    '@type': 'TaskList',
    distance: 0,
    duration: 0,
    polyline: '',
    createdAt: moment().format(),
    updatedAt: moment().format(),
    username,
    items,
  }
}

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

export function removedTasks(state, tasks) {

  return _.differenceWith(
    state,
    tasks,
    taskComparator
  )
}

export function withLinkedTasks(tasks, allTasks) {

  if (!Array.isArray(tasks)) {
    tasks = [ tasks ]
  }

  const newTasks = []
  tasks.forEach(task => {
    // FIXME
    // Make it work when more than 2 tasks are linked together
    if (task.previous) {
      // If previous task is another day, will be null
      const previousTask = _.find(allTasks, t => t['@id'] === task.previous)
      if (previousTask) {
        newTasks.push(previousTask)
      }
      newTasks.push(task)
    } else if (task.next) {
      // If next task is another day, will be null
      const nextTask = _.find(allTasks, t => t['@id'] === task.next)
      newTasks.push(task)
      if (nextTask) {
        newTasks.push(nextTask)
      }
    } else {
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
    alwayShowUnassignedTasks,
    tags,
    hiddenCouriers,
    timeRange,
  } = filters

  const isFinished = _.includes(['DONE', 'FAILED'], task.status)
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
