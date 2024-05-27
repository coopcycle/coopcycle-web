import { default as taskListEntityReducers } from '../taskListEntityReducers'

describe('taskListEntityReducers', () => {

  describe('MODIFY_TASK_LIST_REQUEST', () => {
    describe('task list for this user exists', () => {
      it('should add tasks into a task list', () => {
        expect(taskListEntityReducers(
          {
            ids: [
              'bot_1'
            ],
            entities: {
              'bot_1': {
                '@id': '/api/task_lists/1',
                'username': 'bot_1',
                items: [
                  '/api/tasks/3',
                ]
              },
            },
          },
          {
            type: 'MODIFY_TASK_LIST_REQUEST',
            username: 'bot_1',
            items: [
              '/api/tasks/1',
              '/api/tasks/2',
            ]
          }
        )).toEqual({
          ids: [
            'bot_1'
          ],
          entities: {
            'bot_1': {
              '@id': '/api/task_lists/1',
              'username': 'bot_1',
              items: [
                '/api/tasks/1',
                '/api/tasks/2',
              ]
            },
          },
        })
      })
    })

    describe('task list does not exist', () => {
      it('should ignore action', () => {
        expect(taskListEntityReducers(
          {
            ids: [
              '/api/task_lists/10'
            ],
            entities: {
              '/api/task_lists/10': {
                '@id': '/api/task_lists/10',
                'username': 'bot_10',
                items: []
              },
            },
          },
          {
            type: 'MODIFY_TASK_LIST_REQUEST',
            username: 'bot_1',
            items: [
              '/api/tasks/1',
              '/api/tasks/2',
            ]
          }
        )).toEqual({
          ids: [
            '/api/task_lists/10'
          ],
          entities: {
            '/api/task_lists/10': {
              '@id': '/api/task_lists/10',
              'username': 'bot_10',
              items: []
            },
          },
        })
      })
    })
  })

  describe('MODIFY_TASK_LIST_REQUEST_SUCCESS', () => {
    describe('task list for this user exists (with @id)', () => {
      it('should replace existing task list', () => {
        expect(taskListEntityReducers(
          {
            ids: [
              'bot_1'
            ],
            entities: {
              'bot_1': {
                '@id': '/api/task_lists/1',
                'username': 'bot_1',
                items: [
                  '/api/tasks/3',
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
                '/api/tasks/1',
                '/api/tasks/2',
              ]
            },
          }
        )).toEqual({
          ids: [
            'bot_1'
          ],
          entities: {
            'bot_1': {
              '@id': '/api/task_lists/1',
              'username': 'bot_1',
              items: [
                '/api/tasks/1',
                '/api/tasks/2',
              ]
            },
          },
        })
      })
    })

    describe('temp task list for this user exists (without @id)', () => {
      it('should replace existing task list', () => {
        expect(taskListEntityReducers(
          {
            ids: [
              'bot_1'
            ],
            entities: {
              'bot_1': {
                '@id': 'temp_bot_1',
                'username': 'bot_1',
                items: [
                  '/api/tasks/3',
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
                '/api/tasks/1',
                '/api/tasks/2',
              ]
            },
          }
        )).toEqual({
          ids: [
            'bot_1'
          ],
          entities: {
            'bot_1': {
              '@id': '/api/task_lists/1',
              'username': 'bot_1',
              items: [
                '/api/tasks/1',
                '/api/tasks/2',
              ]
            },
          },
        })
      })
    })

    describe('task list does not exist', () => {
      it('should create a task list', () => {
        expect(taskListEntityReducers(
          {
            ids: [
              'bot_10'
            ],
            entities: {
              'bot_10': {
                '@id': '/api/task_lists/10',
                'username': 'bot_10',
                items: [
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
                '/api/tasks/1',
                '/api/tasks/2',
              ]
            },
          }
        )).toEqual({
          ids: [
            'bot_1',
            'bot_10',
          ],
          entities: {
            'bot_1': {
              '@id': '/api/task_lists/1',
              'username': 'bot_1',
              items: [
                '/api/tasks/1',
                '/api/tasks/2',
              ]
            },
            'bot_10': {
              '@id': '/api/task_lists/10',
              'username': 'bot_10',
              items: [
              ]
            },
          },
        })
      })
    })
  })

  describe('TASK_LISTS_UPDATED', () => {
    describe('task list for this user exists', () => {
      it('should update a task list', () => {
        expect(taskListEntityReducers(
          {
            ids: [
              'bot_1',
            ],
            entities: {
              'bot_1': {
                '@id': '/api/task_lists/1',
                username: 'bot_1',
                date: '2021-03-22',
                items: [
                  '/api/tasks/3',
                ]
              },
            },
          },
          {
            type: 'TASK_LISTS_UPDATED',
            taskList: {
              '@id': '/api/task_lists/1',
              username: 'bot_1',
              date: '2021-03-22',
              items: [
                '/api/tasks/1',
                '/api/tasks/2',
              ],
              distance: 6615,
              duration: 1948,
              polyline: 'polyline',
            },
          }
        )).toEqual({
          ids: [
            'bot_1',
          ],
          entities: {
            'bot_1': {
              '@id': '/api/task_lists/1',
              username: 'bot_1',
              date: '2021-03-22',
              items: [
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
  })
})
