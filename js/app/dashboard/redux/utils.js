import _ from 'lodash'
import { moment } from '../../coopcycle-frontend-js';

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
