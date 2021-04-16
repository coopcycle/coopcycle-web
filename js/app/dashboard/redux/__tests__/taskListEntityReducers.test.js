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
          ids: [
            'bot_1'
          ],
          entities: {
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
          ids: [
            '/api/task_lists/10'
          ],
          entities: {
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
            ids: [
              'bot_1'
            ],
            entities: {
              'bot_1': {
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
          ids: [
            'bot_1'
          ],
          entities: {
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
                  '@id': '_:1',
                  '@type': 'TaskCollectionItem',
                  task: '/api/tasks/1',
                  position: 0
                }, {
                  '@id': '_:2',
                  '@type': 'TaskCollectionItem',
                  task: '/api/tasks/2',
                  position: 1
                }
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
            ids: [
              'bot_10'
            ],
            entities: {
              'bot_10': {
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
          ids: [
            'bot_1',
            'bot_10',
          ],
          entities: {
            'bot_1': {
              '@id': '/api/task_lists/1',
              'username': 'bot_1',
              itemIds: [
                '/api/tasks/1',
                '/api/tasks/2',
              ]
            },
            'bot_10': {
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
              date: '2021-03-22',
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
          ids: [
            'bot_1',
          ],
          entities: {
            'bot_1': {
              '@id': '/api/task_lists/1',
              username: 'bot_1',
              date: '2021-03-22',
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

    describe('task list for this user not exists', () => {
      it('should not update a task list', () => {
        expect(taskListEntityReducers(
          {
            ids: [
              '/api/task_lists/1',
            ],
            entities: {
              '/api/task_lists/1': {
                '@id': '/api/task_lists/1',
                username: 'bot_1',
                date: '2021-03-22',
                itemIds: [
                  '/api/tasks/3',
                ],
                distance: 6000,
                duration: 1900,
              },
            },
          },
          {
            type: 'TASK_LISTS_UPDATED',
            taskLists: [{
              '@id': '/api/task_lists/1',
              username: 'bot_1',
              date: '2021-03-23',
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
          ids: [
            '/api/task_lists/1',
          ],
          entities: {
            '/api/task_lists/1': {
              '@id': '/api/task_lists/1',
              username: 'bot_1',
              date: '2021-03-22',
              itemIds: [
                '/api/tasks/3',
              ],
              distance: 6000,
              duration: 1900,
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
          ids: [
            'bot_1',
          ],
          entities: {
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
        ids: [
          'bot_1',
        ],
        entities: {
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
          ids: [
            'bot_1',
          ],
          entities: {
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
        ids: [
          'bot_1',
        ],
        entities: {
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
          ids: [
            'bot_1',
          ],
          entities: {
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
        ids: [
          'bot_1',
        ],
        entities: {
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
          ids: [
            'bot_1',
          ],
          entities: {
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
        ids: [
          'bot_1',
        ],
        entities: {
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
          ids: [
            'bot_1',
            'bot_2',
          ],
          entities: {
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
        ids: [
          'bot_1',
          'bot_2'
        ],
        entities: {
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
