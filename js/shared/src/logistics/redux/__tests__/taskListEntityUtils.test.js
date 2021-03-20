import {
  findTaskListByUsername,
  findTaskListByTask,
  addAssignedTask,
  removeUnassignedTask,
  addOrReplaceTaskList,
} from '../taskListEntityUtils.js'

describe('taskListEntityUtils', () => {

  describe('findTaskListByUsername', () => {

    it('should return a task list, if it is found ', () => {

      let taskListsById = {
        '/api/task_lists/1': {
          '@id': '/api/task_lists/1',
          'username': 'bot_1',
          itemIds: [
            '/api/tasks/1',
            '/api/tasks/2',
          ],
        },
        '/api/task_lists/2': {
          '@id': '/api/task_lists/2',
          'username': 'bot_2',
          itemIds: [
            '/api/tasks/3',
            '/api/tasks/4',
          ],
        },
      }

      let result = findTaskListByUsername(taskListsById, 'bot_2')

      expect(result).toEqual({
        '@id': '/api/task_lists/2',
        'username': 'bot_2',
        itemIds: [
          '/api/tasks/3',
          '/api/tasks/4',
        ],
      })
    })

    it('should return undefined if a user does not have a task list', () => {

      let taskListsById = {
        '/api/task_lists/1': {
          '@id': '/api/task_lists/1',
          'username': 'bot_1',
          itemIds: [
            '/api/tasks/1',
            '/api/tasks/2',
          ],
        },
      }

      let result = findTaskListByUsername(taskListsById, 'bot_3')

      expect(result).toBeUndefined()
    })
  })

  describe('findTaskListByTask', () => {

    it('should return a task list, if it is found ', () => {

      let taskListsById = {
        '/api/task_lists/1': {
          '@id': '/api/task_lists/1',
          'username': 'bot_1',
          itemIds: [
            '/api/tasks/1',
            '/api/tasks/2',
          ],
        },
        '/api/task_lists/2': {
          '@id': '/api/task_lists/2',
          'username': 'bot_2',
          itemIds: [
            '/api/tasks/3',
            '/api/tasks/4',
          ],
        },
      }

      let task = {
        '@id': '/api/tasks/1',
        id : 1,
        next: '/api/tasks/2',
      }

      let result =  findTaskListByTask(taskListsById, task)

      expect(result).toEqual({
        '@id': '/api/task_lists/1',
        'username': 'bot_1',
        itemIds: [
          '/api/tasks/1',
          '/api/tasks/2',
        ],
      })
    })

    it('should return undefined if task does not belong to any tasklist', () => {

      let taskListsById = {
        '/api/task_lists/1': {
          '@id': '/api/task_lists/1',
          'username': 'bot_1',
          itemIds: [
            '/api/tasks/1',
            '/api/tasks/2',
          ],
        },
        '/api/task_lists/2': {
          '@id': '/api/task_lists/2',
          'username': 'bot_2',
          itemIds: [
            '/api/tasks/3',
            '/api/tasks/4',
          ],
        },
      }

      let task = {
        '@id': '/api/tasks/5',
        id : 1,
        next: '/api/tasks/2',
      }

      let result =  findTaskListByTask(taskListsById, task)

      expect(result).toBeUndefined()
    })
  })

  describe('addAssignedTask', () => {

    it('should add assigned task into existing task list', () => {

      let taskListsById = {
        '/api/task_lists/1': {
          '@id': '/api/task_lists/1',
          'username': 'bot_1',
          itemIds: [
            '/api/tasks/1',
            '/api/tasks/2',
          ]
        },
        '/api/task_lists/2': {
          '@id': '/api/task_lists/2',
          'username': 'bot_2',
          itemIds: [
            '/api/tasks/3',
            '/api/tasks/4',
          ]
        }
      }

      let task = {
        '@id': '/api/tasks/5',
        id: 5,
        isAssigned: true,
        assignedTo: 'bot_1'
      }

      let result = addAssignedTask(taskListsById, task)

      let expectedItems = {
        '/api/task_lists/1': {
          '@id': '/api/task_lists/1',
          'username': 'bot_1',
          itemIds: [
            '/api/tasks/1',
            '/api/tasks/2',
            '/api/tasks/5',
          ]
        },
        '/api/task_lists/2': {
          '@id': '/api/task_lists/2',
          'username': 'bot_2',
          itemIds: [
            '/api/tasks/3',
            '/api/tasks/4',
          ]
        }
      }

      expect(result).toEqual(expectedItems)
      expect(result).not.toBe(taskListsById)
    })

    it('should create a new task list when it does not exist', () => {
      let taskListsById = {
        '/api/task_lists/1': {
          '@id': '/api/task_lists/1',
          'username': 'bot_1',
          itemIds: [
            '/api/tasks/1',
            '/api/tasks/2',
          ]
        },
      }

      let task = {
        '@id': '/api/tasks/3',
        id: 3,
        isAssigned: true,
        assignedTo: 'bot_2'
      }

      let result = addAssignedTask(taskListsById, task)

      expect(result['/api/task_lists/1'].itemIds).toEqual([
        '/api/tasks/1',
        '/api/tasks/2',
      ])
      expect(result['temp_bot_2'].itemIds).toEqual([
        '/api/tasks/3',
      ])
      expect(result).not.toBe(taskListsById)
    })

    it('should unassign task if it was assigned to another courier', () => {
      let taskListsById = {
        '/api/task_lists/1': {
          '@id': '/api/task_lists/1',
          'username': 'bot_1',
          itemIds: [
            '/api/tasks/1',
            '/api/tasks/2',
          ]
        },
        '/api/task_lists/2': {
          '@id': '/api/task_lists/2',
          'username': 'bot_2',
          itemIds: [
            '/api/tasks/3',
            '/api/tasks/4',
          ]
        }
      }

      let task = {
        '@id': '/api/tasks/3',
        id: 5,
        isAssigned: true,
        assignedTo: 'bot_1'
      }

      let result = addAssignedTask(taskListsById, task)

      let expectedItems = {
        '/api/task_lists/1': {
          '@id': '/api/task_lists/1',
          'username': 'bot_1',
          itemIds: [
            '/api/tasks/1',
            '/api/tasks/2',
            '/api/tasks/3',
          ]
        },
        '/api/task_lists/2': {
          '@id': '/api/task_lists/2',
          'username': 'bot_2',
          itemIds: [
            '/api/tasks/4',
          ]
        }
      }

      expect(result).toEqual(expectedItems)
      expect(result).not.toBe(taskListsById)
    })

    it('should not modify a task list if the task is already there', () => {
      let taskListsById = {
        '/api/task_lists/1': {
          '@id': '/api/task_lists/1',
          'username': 'bot_1',
          itemIds: [
            '/api/tasks/1',
            '/api/tasks/2',
          ]
        },
        '/api/task_lists/2': {
          '@id': '/api/task_lists/2',
          'username': 'bot_2',
          itemIds: [
            '/api/tasks/3',
            '/api/tasks/4',
          ]
        }
      }

      let task = {
        '@id': '/api/tasks/1',
        id: 1,
        isAssigned: true,
        assignedTo: 'bot_1'
      }

      let result = addAssignedTask(taskListsById, task)

      let expectedItems = {
        '/api/task_lists/1': {
          '@id': '/api/task_lists/1',
          'username': 'bot_1',
          itemIds: [
            '/api/tasks/1',
            '/api/tasks/2',
          ]
        },
        '/api/task_lists/2': {
          '@id': '/api/task_lists/2',
          'username': 'bot_2',
          itemIds: [
            '/api/tasks/3',
            '/api/tasks/4',
          ]
        }
      }

      expect(result).toEqual(expectedItems)
      expect(result['/api/task_lists/1'].itemIds).toBe(taskListsById['/api/task_lists/1'].itemIds)
    })
  })

  describe('removeUnassignedTask', () => {
    it('should remove unassigned task', () => {
      let taskListsById = {
        '/api/task_lists/1': {
          '@id': '/api/task_lists/1',
          'username': 'bot_1',
          itemIds: [
            '/api/tasks/1',
            '/api/tasks/2',
          ]
        },
        '/api/task_lists/2': {
          '@id': '/api/task_lists/2',
          'username': 'bot_2',
          itemIds: [
            '/api/tasks/3',
            '/api/tasks/4',
          ]
        }
      }

      let task = {
        '@id': '/api/tasks/1',
        id: 1,
        isAssigned: false,
        assignedTo: null,
      }

      let result = removeUnassignedTask(taskListsById, task)

      let expectedItems = {
        '/api/task_lists/1': {
          '@id': '/api/task_lists/1',
          'username': 'bot_1',
          itemIds: [
            '/api/tasks/2',
          ]
        },
        '/api/task_lists/2': {
          '@id': '/api/task_lists/2',
          'username': 'bot_2',
          itemIds: [
            '/api/tasks/3',
            '/api/tasks/4',
          ]
        }
      }

      expect(result).toEqual(expectedItems)
      expect(result).not.toBe(taskListsById)
    })
  })

  describe('addOrReplaceTaskList', () => {
    it('should insert a new task list', () => {
      let taskListsById = {
        '/api/task_lists/1': {
          '@id': '/api/task_lists/1',
          'username': 'bot_1',
          itemIds: [
            '/api/tasks/1',
            '/api/tasks/2',
          ]
        },
      }

      let result = addOrReplaceTaskList(taskListsById, {
        '@id': '/api/task_lists/2',
        'username': 'bot_2',
        itemIds: [
          '/api/tasks/3',
          '/api/tasks/4',
        ]
      })

      expect(result).toEqual({
        '/api/task_lists/1': {
          '@id': '/api/task_lists/1',
          'username': 'bot_1',
          itemIds: [
            '/api/tasks/1',
            '/api/tasks/2',
          ]
        },
        '/api/task_lists/2': {
          '@id': '/api/task_lists/2',
          'username': 'bot_2',
          itemIds: [
            '/api/tasks/3',
            '/api/tasks/4',
          ]
        }
      })
      expect(result).not.toBe(taskListsById)
    })

    it('should replace task list with the same id', () => {
      let taskListsById = {
        '/api/task_lists/1': {
          '@id': '/api/task_lists/1',
          'username': 'bot_1',
          itemIds: [
            '/api/tasks/1',
            '/api/tasks/2',
          ]
        },
        '/api/task_lists/2': {
          '@id': '/api/task_lists/2',
          'username': 'bot_2',
          itemIds: [
            '/api/tasks/3',
            '/api/tasks/4',
          ]
        },
      }

      let result = addOrReplaceTaskList(taskListsById, {
        '@id': '/api/task_lists/1',
        'username': 'bot_1',
        itemIds: [
          '/api/tasks/5',
          '/api/tasks/6',
        ]
      })

      expect(result).toEqual({
        '/api/task_lists/1': {
          '@id': '/api/task_lists/1',
          'username': 'bot_1',
          itemIds: [
            '/api/tasks/5',
            '/api/tasks/6',
          ]
        },
        '/api/task_lists/2': {
          '@id': '/api/task_lists/2',
          'username': 'bot_2',
          itemIds: [
            '/api/tasks/3',
            '/api/tasks/4',
          ]
        }
      })
      expect(result).not.toBe(taskListsById)
    })

    it('should replace task list with the same username', () => {
      let taskListsById = {
        '/api/task_lists/1': {
          '@id': '/api/task_lists/1',
          'username': 'bot_1',
          itemIds: [
            '/api/tasks/1',
            '/api/tasks/2',
          ]
        },
        'temp_bot_2': {
          '@id': 'temp_bot_2',
          'username': 'bot_2',
          itemIds: [
          ]
        },
      }

      let result = addOrReplaceTaskList(taskListsById, {
        '@id': '/api/task_lists/2',
        'username': 'bot_2',
        itemIds: [
          '/api/tasks/3',
          '/api/tasks/4',
        ]
      })

      expect(result).toEqual({
        '/api/task_lists/1': {
          '@id': '/api/task_lists/1',
          'username': 'bot_1',
          itemIds: [
            '/api/tasks/1',
            '/api/tasks/2',
          ]
        },
        '/api/task_lists/2': {
          '@id': '/api/task_lists/2',
          'username': 'bot_2',
          itemIds: [
            '/api/tasks/3',
            '/api/tasks/4',
          ]
        }
      })
      expect(result).not.toBe(taskListsById)
    })
  })
})
