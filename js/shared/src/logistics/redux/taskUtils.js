import _, { mapValues } from 'lodash'
import ColorHash from 'color-hash'

const colorHash = new ColorHash()

export function groupLinkedTasks(tasks) {
  /*
    Given a list of tasks objects, returns the list of linked taskIds for each task.
    
    Output
    [
      '/api/tasks/1': [ '/api/tasks/1', '/api/tasks/2', '/api/tasks/3' ],
      '/api/tasks/2': [ '/api/tasks/1', '/api/tasks/2', '/api/tasks/3' ],
      '/api/tasks/3': [ '/api/tasks/1', '/api/tasks/2', '/api/tasks/3' ]
    ]
  */

  const copy = tasks.slice(0)

  const groups = {}

  while (copy.length > 0) {

    const task = copy.shift()

    if (task.previous) {
      const prevTask = _.find(tasks, t => t['@id'] === task.previous)

      if (prevTask) {
        if (groups[prevTask['@id']]) {

          const newIris = _.reduce(groups[prevTask['@id']], function(result, value) {
            return result.concat([ value ])
          }, [ task['@id'] ])

          newIris.forEach(iri => {
            groups[iri] = newIris
          })

        } else {
          groups[task['@id']] = [ prevTask['@id'], task['@id'] ]
          groups[prevTask['@id']] = [ prevTask['@id'], task['@id'] ]
        }
      }
    }
  }

  return mapValues(groups, (value) => {

    value.sort()

    return value
  })
}

export function mapToColor(tasks) {
  return mapValues(groupLinkedTasks(tasks), taskIds => colorHash.hex(taskIds.join(' ')))
}

export function tasksToIds(tasks) {
  return tasks.map((item) => item['@type'] === 'TaskCollectionItem' ? item.task : item['@id'])
}
