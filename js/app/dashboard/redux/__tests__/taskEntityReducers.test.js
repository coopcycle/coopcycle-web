import { default as taskEntityReducers } from '../taskEntityReducers'

describe('taskEntityReducers', () => {

  // Sent by the jobs that save a lot of tasks at once, e.g. a delivery import
  describe('TASKS_RELOADED', () => {
    it('should add the new tasks and update the others', () => {
      expect(taskEntityReducers(
        {
          ids: [ '/api/tasks/1' ],
          entities: {
            '/api/tasks/1': { '@id': '/api/tasks/1', id: 1, status: 'TODO' },
          },
        },
        {
          type: 'TASKS_RELOADED',
          payload: [
            { '@id': '/api/tasks/1', id: 1, status: 'DONE' },
            { '@id': '/api/tasks/2', id: 2, status: 'TODO' },
          ],
        }
      )).toEqual({
        ids: [ '/api/tasks/1', '/api/tasks/2' ],
        entities: {
          '/api/tasks/1': { '@id': '/api/tasks/1', id: 1, status: 'DONE' },
          '/api/tasks/2': { '@id': '/api/tasks/2', id: 2, status: 'TODO' },
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

  describe('DELETE_GROUP_SUCCESS', () => {

    const state = {
      ids: [
        '/api/tasks/1',
        '/api/tasks/2',
      ],
      entities: {
        '/api/tasks/1': {
          '@id': '/api/tasks/1',
          group: { '@id': '/api/task_groups/1' },
        },
        '/api/tasks/2': {
          '@id': '/api/tasks/2',
          group: null,
        },
      },
    }

    it('should remove the tasks of the group', () => {
      expect(taskEntityReducers(state, {
        type: 'DELETE_GROUP_SUCCESS',
        group: '/api/task_groups/1',
        cancelTasks: true,
      })).toEqual({
        ids: [
          '/api/tasks/2',
        ],
        entities: {
          '/api/tasks/2': {
            '@id': '/api/tasks/2',
            group: null,
          },
        },
      })
    })

    it('should keep the tasks of the group, without group', () => {
      expect(taskEntityReducers(state, {
        type: 'DELETE_GROUP_SUCCESS',
        group: '/api/task_groups/1',
        cancelTasks: false,
      })).toEqual({
        ids: [
          '/api/tasks/1',
          '/api/tasks/2',
        ],
        entities: {
          '/api/tasks/1': {
            '@id': '/api/tasks/1',
            group: null,
          },
          '/api/tasks/2': {
            '@id': '/api/tasks/2',
            group: null,
          },
        },
      })
    })
  })

})
