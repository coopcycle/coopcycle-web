import { default as taskListEntityReducers } from '../redux/taskListEntityReducers'

describe('taskListEntityReducers', () => {

  describe('MODIFY_TASK_LIST_REQUEST', () => {
    it('should add tasks into a task list', () => {
      let initialItems = new Map()
      initialItems.set('bot_1', {
        '@id': '/api/task_lists/1',
        'username': 'bot_1',
        itemIds: [
        ]
      })

      let expectedItems = new Map()
      expectedItems.set('bot_1', {
        '@id': '/api/task_lists/1',
        'username': 'bot_1',
        itemIds: [
          '/api/tasks/1',
          '/api/tasks/2',
        ]
      })

      expect(taskListEntityReducers(
        {
          items: initialItems
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
    it('should add tasks into a task list', () => {
      let initialItems = new Map()
      initialItems.set('bot_1', {
        '@id': '/api/task_lists/1',
        'username': 'bot_1',
        itemIds: [
        ]
      })

      let expectedItems = new Map()
      expectedItems.set('bot_1', {
        '@id': '/api/task_lists/1',
        'username': 'bot_1',
        itemIds: [
          '/api/tasks/1',
          '/api/tasks/2',
        ]
      })

      expect(taskListEntityReducers(
        {
          items: initialItems
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
  
  describe('TASK_LIST_UPDATED', () => {
    it('should update a task list', () => {
      let initialItems = new Map()
      initialItems.set('bot_1', {
        '@id': '/api/task_lists/1',
        'username': 'bot_1',
        itemIds: [
        ]
      })

      let expectedItems = new Map()
      expectedItems.set('bot_1', {
        '@id': '/api/task_lists/1',
        'username': 'bot_1',
        itemIds: [
          '/api/tasks/1',
          '/api/tasks/2',
        ],
        distance: 6615,
        duration: 1948,
        polyline: 'polyline',
      })

      expect(taskListEntityReducers(
        {
          items: initialItems
        },
        {
          type: 'TASK_LIST_UPDATED',
          taskList: {
            '@id': '/api/task_lists/1',
            username: 'bot_1',
            items: [
              {
                task: '/api/tasks/1',
                position: 0,
              }, {
                task: '/api/tasks/2',
                position: 1,
              }
            ],
            distance: 6615,
            duration: 1948,
            polyline: 'polyline',
          },
        }
      )).toEqual({
        items: expectedItems,
      })
    })
  })

  describe('UPDATE_TASK', () => {
    it('should handle assigned task', () => {
      let initialItems = new Map()
      initialItems.set('bot_1', {
        '@id': '/api/task_lists/1',
        'username': 'bot_1',
        itemIds: [
        ]
      })

      let expectedItems = new Map()
      expectedItems.set('bot_1', {
        '@id': '/api/task_lists/1',
        'username': 'bot_1',
        itemIds: [
          '/api/tasks/1',
        ]
      })

      expect(taskListEntityReducers(
        {
          items: initialItems
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

    it('should handle new task already assigned', () => {
      let initialItems = new Map()
      initialItems.set('bot_1', {
        '@id': '/api/task_lists/1',
        'username': 'bot_1',
        itemIds: [
          '/api/tasks/1',
          '/api/tasks/2',
        ]
      })

      let expectedItems = new Map()
      expectedItems.set('bot_1', {
        '@id': '/api/task_lists/1',
        'username': 'bot_1',
        itemIds: [
          '/api/tasks/1',
          '/api/tasks/2',
        ]
      })

      expect(taskListEntityReducers(
        {
          items: initialItems
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

    it('should handle unassigned task', () => {
      let initialItems = new Map()
      initialItems.set('bot_1', {
        '@id': '/api/task_lists/1',
        'username': 'bot_1',
        itemIds: [
          '/api/tasks/1',
        ]
      })

      let expectedItems = new Map()
      expectedItems.set('bot_1', {
        '@id': '/api/task_lists/1',
        'username': 'bot_1',
        itemIds: [
        ]
      })

      expect(taskListEntityReducers(
        {
          items: initialItems
        },
        {
          type: 'UPDATE_TASK',
          task: {
            '@id': '/api/tasks/1',
            id : 1,
            isAssigned: false,
            assignedTo: null,
          },
        }
      )).toEqual({
        items: expectedItems,
      })
    })

    it('should handle unassigned task (not existing)', () => {
      let initialItems = new Map()
      initialItems.set('bot_1', {
        '@id': '/api/task_lists/1',
        'username': 'bot_1',
        itemIds: [
        ]
      })

      let expectedItems = new Map()
      expectedItems.set('bot_1', {
        '@id': '/api/task_lists/1',
        'username': 'bot_1',
        itemIds: [
        ]
      })

      expect(taskListEntityReducers(
        {
          items: initialItems
        },
        {
          type: 'UPDATE_TASK',
          task: {
            '@id': '/api/tasks/1',
            id : 1,
            isAssigned: false,
            assignedTo: null,
          },
        }
      )).toEqual({
        items: expectedItems,
      })
    })

    it('should handle reassigned task', () => {
      let initialItems = new Map()
      initialItems.set('bot_1', {
        '@id': '/api/task_lists/1',
        'username': 'bot_1',
        itemIds: [
        ]
      })
      initialItems.set('bot_2', {
        '@id': '/api/task_lists/2',
        'username': 'bot_2',
        itemIds: [
          '/api/tasks/1',
        ]
      })

      let expectedItems = new Map()
      expectedItems.set('bot_1', {
        '@id': '/api/task_lists/1',
        'username': 'bot_1',
        itemIds: [
          '/api/tasks/1',
        ]
      })
      expectedItems.set('bot_2', {
        '@id': '/api/task_lists/2',
        'username': 'bot_2',
        itemIds: [
        ]
      })

      expect(taskListEntityReducers(
        {
          items: initialItems
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
