import { default as taskEntityReducers } from '../taskEntityReducers'

describe('taskEntityReducers', () => {

  describe('MODIFY_TASK_LIST_REQUEST', () => {
    it('should update tasks', () => {
      expect(taskEntityReducers(
        {
          ids: [
            '/api/tasks/1',
            '/api/tasks/2'
          ],
          entities: {
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
        ids: [
          '/api/tasks/1',
          '/api/tasks/2'
        ],
        entities: {
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
          ids: [
            '/api/tasks/1',
            '/api/tasks/2'
          ],
          entities: {
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
        ids: [
          '/api/tasks/1',
          '/api/tasks/2'
        ],
        entities: {
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
    describe('task exists', () => {
      it('should update task', () => {
        expect(taskEntityReducers(
          {
            ids: [
              '/api/tasks/1'
            ],
            entities: {
              '/api/tasks/1': {
                '@id': '/api/tasks/1',
                id : 1,
                isAssigned: false,
                position: 2,
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
          ids: [
            '/api/tasks/1'
          ],
          entities: {
            '/api/tasks/1': {
              '@id': '/api/tasks/1',
              id : 1,
              isAssigned: true,
              assignedTo: 'bot_1',
              position: 2,
            },
          },
        })
      })
      it('should remove task', () => {
        expect(taskEntityReducers(
          {
            ids: [
              '/api/tasks/1',
              '/api/tasks/2'
            ],
            entities: {
              '/api/tasks/1': {
                '@id': '/api/tasks/1',
              },
              '/api/tasks/2': {
                '@id': '/api/tasks/2',
              },
            },
          },
          {
            type: 'REMOVE_TASK',
            task: {
              '@id': '/api/tasks/1',
            },
          }
        )).toEqual({
          ids: [
            '/api/tasks/2'
          ],
          entities: {
            '/api/tasks/2': {
              '@id': '/api/tasks/2',
            },
          },
        })
      })
    })

    describe('task does not exist', () => {
      it('should add task', () => {
        expect(taskEntityReducers(
          {
            ids: [],
            entities: {},
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
          ids: [
            '/api/tasks/1',
          ],
          entities: {
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

})
