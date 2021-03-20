import {
  groupLinkedTasks,
  tasksToIds,
  addOrReplaceTasks,
} from '../taskUtils.js'

describe('taskUtils', () => {
  describe('groupLinkedTasks', () => {

    it('should group when tasks are ordered', () => {

      const tasks = [
        {
          '@id': '/api/tasks/1',
          id : 1,
          next: '/api/tasks/2',
        }, {
          '@id': '/api/tasks/2',
          id : 2,
          previous: '/api/tasks/1',
        }, {
          '@id': '/api/tasks/3',
          id : 3,
        }
      ]

      const groups = groupLinkedTasks(tasks)

      expect(groups).toEqual({
        '/api/tasks/1': [ 1, 2 ],
        '/api/tasks/2': [ 1, 2 ],
      })
    })

    it('should group when tasks are not ordered', () => {

      const tasks = [
        {
          '@id': '/api/tasks/2',
          id : 2,
          previous: '/api/tasks/1',
        }, {
          '@id': '/api/tasks/1',
          id : 1,
          next: '/api/tasks/2',
        },  {
          '@id': '/api/tasks/3',
          id : 3,
        }
      ]

      const groups = groupLinkedTasks(tasks)

      expect(groups).toEqual({
        '/api/tasks/1': [ 1, 2 ],
        '/api/tasks/2': [ 1, 2 ],
      })
    })

    it('should group when there are more than 2 tasks', () => {

      const tasks = [
        {
          '@id': '/api/tasks/1',
          id : 1,
          next: '/api/tasks/2',
        }, {
          '@id': '/api/tasks/2',
          id : 2,
          previous: '/api/tasks/1',
          next: '/api/tasks/3',
        }, {
          '@id': '/api/tasks/3',
          id : 3,
          previous: '/api/tasks/2',
        }
      ]

      const groups = groupLinkedTasks(tasks)

      expect(groups).toEqual({
        '/api/tasks/1': [ 1, 2, 3 ],
        '/api/tasks/2': [ 1, 2, 3 ],
        '/api/tasks/3': [ 1, 2, 3 ],
      })
    })

    it('should group multiple', () => {

      const tasks = [
        {
          '@id': '/api/tasks/1',
          id : 1,
          next: '/api/tasks/2',
        }, {
          '@id': '/api/tasks/2',
          id : 2,
          previous: '/api/tasks/1',
        }, {
          '@id': '/api/tasks/3',
          id : 3,
        },{
          '@id': '/api/tasks/4',
          id : 4,
          next: '/api/tasks/5',
        }, {
          '@id': '/api/tasks/5',
          id : 5,
          previous: '/api/tasks/4',
        }
      ]

      const groups = groupLinkedTasks(tasks)

      expect(groups).toEqual({
        '/api/tasks/1': [ 1, 2 ],
        '/api/tasks/2': [ 1, 2 ],
        '/api/tasks/4': [ 4, 5 ],
        '/api/tasks/5': [ 4, 5 ],
      })
    })

  })

  describe('tasksToIds', () => {

    it('should map tasks to task ids', () => {

      let tasks = [
        {
          '@id': '/api/tasks/1',
          id : 1,
        }, {
          '@id': '/api/tasks/2',
          id : 2,
        }
      ]

      let ids = tasksToIds(tasks)

      expect(ids).toEqual([
        '/api/tasks/1',
        '/api/tasks/2',
      ])
    })
  })

  describe('addOrReplaceTasks', () => {
    it('should update an existing task', () => {

      let items = {
        '/api/tasks/1': {
          '@id': '/api/tasks/1',
          id : 1,
          next: '/api/tasks/2',
        },
        '/api/tasks/2': {
          '@id': '/api/tasks/2',
          id : 2,
          previous: '/api/tasks/1',
        }
      }

      let task = {
        '@id': '/api/tasks/2',
        id : 2,
        previous: '/api/tasks/1',
        next: '/api/tasks/4',
      }

      let expectedResult = {
        '/api/tasks/1': {
          '@id': '/api/tasks/1',
          id : 1,
          next: '/api/tasks/2',
        },
        '/api/tasks/2': {
          '@id': '/api/tasks/2',
          id : 2,
          previous: '/api/tasks/1',
          next: '/api/tasks/4',
        }
      }

      let result = addOrReplaceTasks(items, [task])

      expect(result).toEqual(expectedResult)
      expect(result).not.toBe(items)
    })

    it('should insert a new task', () => {

      let items = {
        '/api/tasks/1': {
          '@id': '/api/tasks/1',
          id : 1,
          next: '/api/tasks/2',
        },
        '/api/tasks/2': {
          '@id': '/api/tasks/2',
          id : 2,
          next: '/api/tasks/3',
        }
      }

      let task = {
        '@id': '/api/tasks/3',
        id : 3,
        next: '/api/tasks/4',
      }

      let expectedResult = {
        '/api/tasks/1': {
          '@id': '/api/tasks/1',
          id : 1,
          next: '/api/tasks/2',
        },
        '/api/tasks/2': {
          '@id': '/api/tasks/2',
          id : 2,
          next: '/api/tasks/3',
        },
        '/api/tasks/3': {
          '@id': '/api/tasks/3',
          id : 3,
          next: '/api/tasks/4',
        }
      }

      let result = addOrReplaceTasks(items, [task])

      expect(result).toEqual(expectedResult)
      expect(result).not.toBe(items)
    })
  })

})
