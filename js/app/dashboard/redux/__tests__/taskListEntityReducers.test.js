import { default as taskListEntityReducers } from '../taskListEntityReducers'

describe('taskListEntityReducers', () => {

  describe('MODIFY_TASK_LIST_REQUEST', () => {
    it('should add tasks into a task list', () => {
      expect(taskListEntityReducers(
        {
          byUsername: {
            'bot_1': {
              '@id': '/api/task_lists/1',
              'username': 'bot_1',
              itemIds: [
              ]
            },
          },
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
        byUsername: {
          'bot_1': {
            '@id': '/api/task_lists/1',
            'username': 'bot_1',
            itemIds: [
              '/api/tasks/1',
              '/api/tasks/2',
            ]
          },
        },
      })
    })
  })

  describe('MODIFY_TASK_LIST_REQUEST_SUCCESS', () => {
    it('should add tasks into a task list', () => {
      expect(taskListEntityReducers(
        {
          byUsername: {
            'bot_1': {
              '@id': '/api/task_lists/1',
              'username': 'bot_1',
              itemIds: [
              ]
            },
          },
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
        byUsername: {
          'bot_1': {
            '@id': '/api/task_lists/1',
            'username': 'bot_1',
            itemIds: [
              '/api/tasks/1',
              '/api/tasks/2',
            ]
          },
        },
      })
    })
  })

  describe('TASK_LIST_UPDATED', () => {
    it('should update a task list', () => {
      expect(taskListEntityReducers(
        {
          byUsername: {
            'bot_1': {
              '@id': '/api/task_lists/1',
              'username': 'bot_1',
              itemIds: [
              ]
            },
          },
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
        byUsername: {
          'bot_1': {
            '@id': '/api/task_lists/1',
            'username': 'bot_1',
            itemIds: [
              '/api/tasks/1',
              '/api/tasks/2',
            ],
            distance: 6615,
            duration: 1948,
            polyline: 'polyline',
          },
        },
      })
    })
  })

  describe('UPDATE_TASK', () => {
    it('should handle assigned task', () => {
      expect(taskListEntityReducers(
        {
          byUsername: {
            'bot_1': {
              '@id': '/api/task_lists/1',
              'username': 'bot_1',
              itemIds: [
              ]
            },
          },
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
        byUsername: {
          'bot_1': {
            '@id': '/api/task_lists/1',
            'username': 'bot_1',
            itemIds: [
              '/api/tasks/1',
            ]
          },
        },
      })
    })

    it('should handle new task already assigned', () => {
      expect(taskListEntityReducers(
        {
          byUsername: {
            'bot_1': {
              '@id': '/api/task_lists/1',
              'username': 'bot_1',
              itemIds: [
                '/api/tasks/1',
                '/api/tasks/2',
              ]
            },
          },
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
        byUsername: {
          'bot_1': {
            '@id': '/api/task_lists/1',
            'username': 'bot_1',
            itemIds: [
              '/api/tasks/1',
              '/api/tasks/2',
            ]
          },
        },
      })
    })

    it('should handle unassigned task', () => {
      expect(taskListEntityReducers(
        {
          byUsername: {
            'bot_1': {
              '@id': '/api/task_lists/1',
              'username': 'bot_1',
              itemIds: [
                '/api/tasks/1',
              ]
            },
          },
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
        byUsername: {
          'bot_1': {
            '@id': '/api/task_lists/1',
            'username': 'bot_1',
            itemIds: [
            ]
          },
        },
      })
    })

    it('should handle unassigned task (not existing)', () => {
      expect(taskListEntityReducers(
        {
          byUsername: {
            'bot_1': {
              '@id': '/api/task_lists/1',
              'username': 'bot_1',
              itemIds: [
              ]
            },
          },
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
        byUsername: {
          'bot_1': {
            '@id': '/api/task_lists/1',
            'username': 'bot_1',
            itemIds: [
            ]
          },
        },
      })
    })

    it('should handle reassigned task', () => {
      expect(taskListEntityReducers(
        {
          byUsername: {
            'bot_1': {
              '@id': '/api/task_lists/1',
              'username': 'bot_1',
              itemIds: [
              ]
            },
            'bot_2': {
              '@id': '/api/task_lists/2',
              'username': 'bot_2',
              itemIds: [
                '/api/tasks/1',
              ]
            },
          },
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
        byUsername: {
          'bot_1': {
            '@id': '/api/task_lists/1',
            'username': 'bot_1',
            itemIds: [
              '/api/tasks/1',
            ]
          },
          'bot_2': {
            '@id': '/api/task_lists/2',
            'username': 'bot_2',
            itemIds: [
            ]
          },
        },
      })
    })

  })

})
