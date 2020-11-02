import { default as taskEntityReducers } from '../redux/taskEntityReducers'

describe('taskEntityReducers', () => {

  describe('MODIFY_TASK_LIST_REQUEST', () => {
    it('should update tasks', () => {
      let initialItems = new Map()
      initialItems.set('/api/tasks/1', {
        '@id': '/api/tasks/1',
        id : 1,
        next: '/api/tasks/2',
        isAssigned: false,
      })
      initialItems.set('/api/tasks/2', {
        '@id': '/api/tasks/2',
        id : 2,
        previous: '/api/tasks/1',
        isAssigned: false,
      })

      let expectedItems = new Map()
      expectedItems.set('/api/tasks/1', {
        '@id': '/api/tasks/1',
        id : 1,
        next: '/api/tasks/2',
        isAssigned: true,
        assignedTo: 'bot_1'
      })
      expectedItems.set('/api/tasks/2', {
        '@id': '/api/tasks/2',
        id : 2,
        previous: '/api/tasks/1',
        isAssigned: true,
        assignedTo: 'bot_1'
      })

      expect(taskEntityReducers(
        {
          items: new Map()
        },
        {
          type: 'MODIFY_TASK_LIST_REQUEST',
          username: 'bot_1',
          tasks: [
            {
              '@id': '/api/tasks/1',
              id : 1,
              next: '/api/tasks/2',
              isAssigned: true,
              assignedTo: 'bot_1'
            }, {
              '@id': '/api/tasks/2',
              id : 2,
              previous: '/api/tasks/1',
              isAssigned: true,
              assignedTo: 'bot_1'
            }
          ]
        }
      )).toEqual({
        items: expectedItems,
      })
    })
  })

  describe('MODIFY_TASK_LIST_REQUEST_SUCCESS', () => {
    it('should update tasks', () => {

      let initialItems = new Map()
      initialItems.set('/api/tasks/1', {
        '@id': '/api/tasks/1',
        id : 1,
        next: '/api/tasks/2',
        isAssigned: false,
      })
      initialItems.set('/api/tasks/2', {
        '@id': '/api/tasks/2',
        id : 2,
        previous: '/api/tasks/1',
        isAssigned: false,
      })

      let expectedItems = new Map()
      expectedItems.set('/api/tasks/1', {
        '@id': '/api/tasks/1',
        id : 1,
        next: '/api/tasks/2',
        isAssigned: true,
        assignedTo: 'bot_1'
      })
      expectedItems.set('/api/tasks/2', {
        '@id': '/api/tasks/2',
        id : 2,
        previous: '/api/tasks/1',
        isAssigned: true,
        assignedTo: 'bot_1'
      })

      expect(taskEntityReducers(
        {
          items: new Map()
        },
        {
          type: 'MODIFY_TASK_LIST_REQUEST_SUCCESS',
          taskList: {
            '@id': '/api/task_lists/1',
            username: 'bot_1',
            items: [
              {
                '@id': '/api/tasks/1',
                id : 1,
                next: '/api/tasks/2',
                isAssigned: true,
                assignedTo: 'bot_1'
              }, {
                '@id': '/api/tasks/2',
                id : 2,
                previous: '/api/tasks/1',
                isAssigned: true,
                assignedTo: 'bot_1'
              }
            ]
          },
        }
      )).toEqual({
        items: expectedItems,
      })
    })
  })

  describe('UPDATE_TASK', () => {
    it('should update task', () => {

      let initialItems = new Map()
      initialItems.set('/api/tasks/1', {
        '@id': '/api/tasks/1',
        id : 1,
        isAssigned: false,
      })

      let expectedItems = new Map()
      expectedItems.set('/api/tasks/1', {
        '@id': '/api/tasks/1',
        id : 1,
        isAssigned: true,
        assignedTo: 'bot_1'
      })

      expect(taskEntityReducers(
        {
          items: new Map()
        },
        {
          type: 'UPDATE_TASK',
          task: {
            '@id': '/api/tasks/1',
            id : 1,
            isAssigned: true,
            assignedTo: 'bot_1'
          },
        }
      )).toEqual({
        items: expectedItems,
      })
    })
  })

})
