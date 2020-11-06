import { default as taskEntityReducers } from '../taskEntityReducers'

describe('taskEntityReducers', () => {

  describe('MODIFY_TASK_LIST_REQUEST', () => {
    it('should update tasks', () => {
      expect(taskEntityReducers(
        {
          byId: {
            '/api/tasks/1': {
              '@id': '/api/tasks/1',
              id : 1,
              next: '/api/tasks/2',
              isAssigned: false,
            },
            '/api/tasks/2': {
              '@id': '/api/tasks/2',
              id : 2,
              previous: '/api/tasks/1',
              isAssigned: false,
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
        byId: {
          '/api/tasks/1': {
            '@id': '/api/tasks/1',
            id : 1,
            next: '/api/tasks/2',
            isAssigned: true,
            assignedTo: 'bot_1'
          },
          '/api/tasks/2': {
            '@id': '/api/tasks/2',
            id : 2,
            previous: '/api/tasks/1',
            isAssigned: true,
            assignedTo: 'bot_1'
          },
        },
      })
    })
  })

  describe('MODIFY_TASK_LIST_REQUEST_SUCCESS', () => {
    it('should update tasks', () => {
      expect(taskEntityReducers(
        {
          byId: {
            '/api/tasks/1': {
              '@id': '/api/tasks/1',
              id : 1,
              next: '/api/tasks/2',
              isAssigned: false,
            },
            '/api/tasks/2': {
              '@id': '/api/tasks/2',
              id : 2,
              previous: '/api/tasks/1',
              isAssigned: false,
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
        byId: {
          '/api/tasks/1': {
            '@id': '/api/tasks/1',
            id : 1,
            next: '/api/tasks/2',
            isAssigned: true,
            assignedTo: 'bot_1'
          },
          '/api/tasks/2': {
            '@id': '/api/tasks/2',
            id : 2,
            previous: '/api/tasks/1',
            isAssigned: true,
            assignedTo: 'bot_1'
          },
        },
      })
    })
  })

  describe('UPDATE_TASK', () => {
    it('should update task', () => {
      expect(taskEntityReducers(
        {
          byId: {
            '/api/tasks/1': {
              '@id': '/api/tasks/1',
              id : 1,
              isAssigned: false,
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
        byId: {
          '/api/tasks/1': {
            '@id': '/api/tasks/1',
            id : 1,
            isAssigned: true,
            assignedTo: 'bot_1'
          },
        },
      })
    })
  })

})
