import _, { mapValues } from 'lodash'

const COLORS_LIST = [
  '#213ab2',
  '#b2213a',
  '#5221b2',
  '#93c63f',
  '#b22182',
  '#3ab221',
  '#b25221',
  '#2182b2',
  '#3ab221',
  '#9c21b2',
  '#c63f4f',
  '#b2217f',
  '#82b221',
  '#5421b2',
  '#3f93c6',
  '#21b252',
  '#c6733f'
]

const integerToColor = value => COLORS_LIST[(value % COLORS_LIST.length)]

export function groupLinkedTasks(tasks) {
  const tasksWithPreviousOrNext = _.filter(tasks, t => t.previous || t.next)

  const lookup = (groups, task) => {
    return _.find(groups, (tasks) => _.includes(tasks, task.id)) || [ task.id ]
  }

  const groups = {}
  while (tasksWithPreviousOrNext.length > 0) {
    const task = tasksWithPreviousOrNext.shift()

    groups[task['@id']] = lookup(groups, task)

    if (task.next) {
      const nextTask = _.find(tasksWithPreviousOrNext, t => t['@id'] === task.next)
      if (nextTask) {
        groups[task['@id']].push(nextTask.id)
        groups[nextTask['@id']] = groups[task['@id']].slice()
      }
    }

    if (task.previous) {
      const prevTask = _.find(tasksWithPreviousOrNext, t => t['@id'] === task.previous)
      if (prevTask) {
        groups[task['@id']].unshift(prevTask.id)
        groups[prevTask['@id']] = groups[task['@id']].slice()
      }
    }
  }

  return groups
}

export function mapToColor(tasks) {
  return mapValues(groupLinkedTasks(tasks), taskIds => integerToColor(taskIds.reduce((accumulator, value) => accumulator + value)))
}

export function tasksToIds(tasks) {
  return tasks.map((item) => item['@id'])
}

export function addOrReplaceTasks(tasksById, tasks) {
  let newItems = Object.assign({}, tasksById)

  for (let task of tasks) {
    newItems[task['@id']] = task
  }

  return newItems
}
