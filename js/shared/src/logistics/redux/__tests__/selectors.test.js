import {
  selectSelectedDate,
  selectAllTasks,
  selectAssignedTasks,
  selectUnassignedTasks,
  selectTasksWithColor,
} from '../selectors';

import moment from '../../../moment';

describe('Selectors', () => {
  let date = moment().format('YYYY-MM-DD')

  let baseState = {
    logistics: {
      date,
      entities: {
        tasks: {
          ids: [
            '/api/tasks/1',
            '/api/tasks/2',
            '/api/tasks/3',
            '/api/tasks/4'
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
            '/api/tasks/3': {
              '@id': '/api/tasks/3',
              id : 3,
            },
            '/api/tasks/4': {
              '@id': '/api/tasks/4',
              id : 4,
            },
          },
        },
        taskLists: {
          ids: [
            'bot_1',
            'bot_2'
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
            'bot_2': {
              '@id': '/api/task_lists/2',
              'username': 'bot_2',
              items: [
                '/api/tasks/3',
              ]
            },
          },
        },
        tours: {
          ids: [],
          entities: {}
        }
      },
      ui: {
        taskListsLoading: false,
      }
    }
  }

  describe('selectSelectedDate', () => {
    it('should return selected date', () => {
      expect(selectSelectedDate(baseState)).toEqual(date)
    })
  })

  describe('selectAllTasks', () => {
    it('should return all tasks', () => {
      expect(selectAllTasks(baseState)).toEqual([
        {
          '@id': '/api/tasks/1',
          id : 1,
          next: '/api/tasks/2',
        },
        {
          '@id': '/api/tasks/2',
          id : 2,
          previous: '/api/tasks/1',
        },
        {
          '@id': '/api/tasks/3',
          id : 3,
        },
        {
          '@id': '/api/tasks/4',
          id : 4,
        },
      ])
    })
  })

  describe('selectAssignedTasks', () => {
    it('should return assigned tasks', () => {
      expect(selectAssignedTasks(baseState)).toEqual([
        {
          "@id": "/api/tasks/1",
          "id": 1,
          "next": "/api/tasks/2",
        },
          {
          "@id": "/api/tasks/2",
          "id": 2,
          "previous": "/api/tasks/1",
        },
          {
          "@id": "/api/tasks/3",
          "id": 3,
        },
      ])
    })
  })

  describe('selectUnassignedTasks', () => {
    it('should return unassigned tasks', () => {
      expect(selectUnassignedTasks(baseState)).toEqual([
        {
          '@id': '/api/tasks/4',
          id : 4,
        },
      ])
    })

    it('excludes a task assigned to a courier even when it is not in a task list for the day', () => {
      // e.g. a month-spanning task returned by api/tasks?date=D because its
      // [doneAfter, doneBefore] window overlaps D, while its task list item
      // lives on another day (see #874). It must not show up as unassigned.
      const state = {
        logistics: {
          date,
          entities: {
            tasks: {
              ids: ['/api/tasks/5'],
              entities: {
                '/api/tasks/5': {
                  '@id': '/api/tasks/5',
                  id: 5,
                  assignedTo: 'bot_1',
                },
              },
            },
            taskLists: { ids: [], entities: {} },
            tours: { ids: [], entities: {} },
          },
          ui: { taskListsLoading: false },
        },
      }

      expect(selectUnassignedTasks(state)).toEqual([])
    })
  })

  describe('selectTasksWithColor', () => {
    it('should return tasks with a color tag', () => {
      expect(selectTasksWithColor(baseState)).toEqual({
        '/api/tasks/1': '#6c87e0',
        '/api/tasks/2': '#6c87e0',
      })
    })
  })
})
