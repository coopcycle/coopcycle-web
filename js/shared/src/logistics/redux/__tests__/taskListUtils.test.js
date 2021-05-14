import {
  replaceTasksWithIds,
} from '../taskListUtils.js'

describe('taskListUtils', () => {

  describe('replaceTasksWithIds', () => {

    it('should remove items and add itemIds in a task list', () => {

      let taskList = {
        '@id': '/api/task_lists/1',
        username: 'bot_1',
        items: [
          {
            '@id': '/api/tasks/1',
            id : 1,
          }, {
            '@id': '/api/tasks/2',
            id : 2,
          }
        ]
      }

      let result =  replaceTasksWithIds(taskList)

      expect(result).toEqual({
        '@id': '/api/task_lists/1',
        username: 'bot_1',
        itemIds: [
          '/api/tasks/1',
          '/api/tasks/2',
        ]
      })
      expect(result).not.toBe(taskList)
    })
  })
})
