import { default as taskListEntityReducers } from '../taskListEntityReducers'

describe('taskListEntityReducers', () => {

  describe('CREATE_TASK_LIST_SUCCESS', () => {
    it('should add a task list', () => {
      expect(taskListEntityReducers(
          {
            ids: [],
            entities: {},
          },
          {
            type: 'CREATE_TASK_LIST_SUCCESS',
            payload: {
              '@id': '/api/task_lists/1',
              'username': 'bot_1',
              items: [
                '/api/tasks/1',
                '/api/tasks/2',
              ]
            }
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

})
