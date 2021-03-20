import { default as taskListEntityReducers } from '../taskListEntityReducers'

describe('taskListEntityReducers', () => {

  describe('MODIFY_TASK_LIST_REQUEST', () => {
    describe('task list for this user exists', () => {
      it('should add tasks into a task list', () => {
        expect(taskListEntityReducers(
          {
            byId: {
              '/api/task_lists/1': {
                '@id': '/api/task_lists/1',
                'username': 'bot_1',
                itemIds: [
                  '/api/tasks/3',
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
          byId: {
            '/api/task_lists/1': {
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

    describe('task list does not exist', () => {
      it('should ignore action', () => {
        expect(taskListEntityReducers(
          {
            byId: {
              '/api/task_lists/10': {
                '@id': '/api/task_lists/10',
                'username': 'bot_10',
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
          byId: {
            '/api/task_lists/10': {
              '@id': '/api/task_lists/10',
              'username': 'bot_10',
              itemIds: [
              ]
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
            byId: {
              '/api/task_lists/1': {
                '@id': '/api/task_lists/1',
                'username': 'bot_1',
                itemIds: [
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
            '/api/task_lists/1': {
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

    describe('temp task list for this user exists (without @id)', () => {
      it('should replace existing task list', () => {
        expect(taskListEntityReducers(
          {
            byId: {
              'temp_bot_1': {
                '@id': 'temp_bot_1',
                'username': 'bot_1',
                itemIds: [
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
            '/api/task_lists/1': {
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

    describe('task list does not exist', () => {
      it('should create a task list', () => {
        expect(taskListEntityReducers(
          {
            byId: {
              '/api/task_lists/10': {
                '@id': '/api/task_lists/10',
                'username': 'bot_10',
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
          byId: {
            '/api/task_lists/1': {
              '@id': '/api/task_lists/1',
              'username': 'bot_1',
              itemIds: [
                '/api/tasks/1',
                '/api/tasks/2',
              ]
            },
            '/api/task_lists/10': {
              '@id': '/api/task_lists/10',
              'username': 'bot_10',
              itemIds: [
              ]
            },
          },
        })
      })
    })
  })

  describe('TASK_LIST_UPDATED', () => {
    describe('task list for this user exists (with @id)', () => {
      it('should update a task list', () => {
        expect(taskListEntityReducers(
          {
            byId: {
              '/api/task_lists/1': {
                '@id': '/api/task_lists/1',
                'username': 'bot_1',
                itemIds: [
                  '/api/tasks/3',
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
          byId: {
            '/api/task_lists/1': {
              '@id': '/api/task_lists/1',
              'username': 'bot_1',
              itemIds: [
                '/api/tasks/3',
              ],
              distance: 6615,
              duration: 1948,
              polyline: 'polyline',
            },
          },
        })
      })
    })

    describe('temp task list for this user exists (without @id)', () => {
      it('should update a task list', () => {
        expect(taskListEntityReducers(
          {
            byId: {
              'temp_bot_1': {
                '@id': 'temp_bot_1',
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
          byId: {
            'temp_bot_1': {
              '@id': 'temp_bot_1',
              'username': 'bot_1',
              itemIds: [
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
            byId: {
              '/api/task_lists/10': {
                '@id': '/api/task_lists/10',
                'username': 'bot_10',
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
          byId: {
            '/api/task_lists/10': {
              '@id': '/api/task_lists/10',
              'username': 'bot_10',
              itemIds: [
              ]
            },
          },
        })
      })
    })
  })

  describe('TASK_LISTS_UPDATED', () => {
    describe('task list for this user exists (with @id)', () => {
      it('should update a task list', () => {
        expect(taskListEntityReducers(
          {
            byId: {
              '/api/task_lists/1': {
                '@id': '/api/task_lists/1',
                'username': 'bot_1',
                itemIds: [
                  '/api/tasks/3',
                ]
              },
            },
          },
          {
            type: 'TASK_LISTS_UPDATED',
            taskLists: [{
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
            }],
          }
        )).toEqual({
          byId: {
            '/api/task_lists/1': {
              '@id': '/api/task_lists/1',
              'username': 'bot_1',
              itemIds: [
                '/api/tasks/3',
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

  describe('UPDATE_TASK', () => {
    it('should handle assigned task', () => {
      expect(taskListEntityReducers(
        {
          byId: {
            '/api/task_lists/1': {
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
        byId: {
          '/api/task_lists/1': {
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
          byId: {
            '/api/task_lists/1': {
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
        byId: {
          '/api/task_lists/1': {
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
          byId: {
            '/api/task_lists/1': {
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
        byId: {
          '/api/task_lists/1': {
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
          byId: {
            '/api/task_lists/1': {
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
        byId: {
          '/api/task_lists/1': {
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
          byId: {
            '/api/task_lists/1': {
              '@id': '/api/task_lists/1',
              'username': 'bot_1',
              itemIds: [
              ]
            },
            '/api/task_lists/2': {
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
        byId: {
          '/api/task_lists/1': {
            '@id': '/api/task_lists/1',
            'username': 'bot_1',
            itemIds: [
              '/api/tasks/1',
            ]
          },
          '/api/task_lists/2': {
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
