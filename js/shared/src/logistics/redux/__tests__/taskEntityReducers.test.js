import { default as taskEntityReducers } from '../taskEntityReducers'

describe('taskEntityReducers', () => {

  describe('CREATE_TASK_LIST_SUCCESS', () => {
    it('should add tasks', () => {
      expect(taskEntityReducers(
          {
            ids: [],
            entities: {}
          },
          {
            type: 'CREATE_TASK_LIST_SUCCESS',
            payload: {
              '@id': '/api/task_lists/1',
              'username': 'bot_1',
              items: [
                {
                  '@id': '/api/tasks/1',
                  id : 1,
                  next: '/api/tasks/2',
                }, {
                  '@id': '/api/tasks/2',
                  id : 2,
                  previous: '/api/tasks/1',
                }
              ]
            }
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
          },
          '/api/tasks/2': {
            '@id': '/api/tasks/2',
            id : 2,
            previous: '/api/tasks/1',
          }
        },
      })
    })

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
              },
              '/api/tasks/2': {
                '@id': '/api/tasks/2',
                id : 2,
              },
            }
          },
          {
            type: 'CREATE_TASK_LIST_SUCCESS',
            payload: {
              '@id': '/api/task_lists/1',
              'username': 'bot_1',
              items: [
                {
                  '@id': '/api/tasks/1',
                  id : 1,
                  next: '/api/tasks/2',
                }, {
                  '@id': '/api/tasks/2',
                  id : 2,
                  previous: '/api/tasks/1',
                }
              ]
            }
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
          },
          '/api/tasks/2': {
            '@id': '/api/tasks/2',
            id : 2,
            previous: '/api/tasks/1',
          },
        },
      })
    })
  })

})
