import { default as taskEntityReducers } from '../taskEntityReducers'

describe('taskEntityReducers', () => {

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
        keepTasks: false,
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
        keepTasks: true,
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
