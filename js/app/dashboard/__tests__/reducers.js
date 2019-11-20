import { combinedTasks, unassignedTasks, taskLists } from '../redux/reducers'
import moment from 'moment'

describe('combinedTasks reducer', () => {

  it('should handle assigned task', () => {

    expect(
      combinedTasks({
        allTasks: [],
        unassignedTasks: [
          { '@id': 1, isAssigned: false }
        ],
        taskLists: [
          { username: 'bob', items: [] }
        ]
      }, {
        type: 'UPDATE_TASK',
        task: {
          '@id': 1,
          isAssigned: true,
          assignedTo: 'bob'
        }
      })
    ).toEqual({
      allTasks: [],
      unassignedTasks: [],
      taskLists: [
        { username: 'bob', items: [{
          '@id': 1,
          isAssigned: true,
          assignedTo: 'bob'
        }] }
      ]
    })

  })

  it('should handle assigned task (not existing)', () => {

    expect(
      combinedTasks({
        allTasks: [],
        unassignedTasks: [],
        taskLists: [
          { username: 'bob', items: [] }
        ]
      }, {
        type: 'UPDATE_TASK',
        task: {
          '@id': 1,
          isAssigned: true,
          assignedTo: 'bob'
        }
      })
    ).toEqual({
      allTasks: [],
      unassignedTasks: [],
      taskLists: [
        { username: 'bob', items: [{
          '@id': 1,
          isAssigned: true,
          assignedTo: 'bob'
        }] }
      ]
    })

    expect(
      combinedTasks({
        allTasks: [],
        unassignedTasks: [],
        taskLists: [
          { username: 'bob', items: [{
            '@id': 1,
            isAssigned: true,
            assignedTo: 'bob'
          }] }
        ]
      }, {
        type: 'UPDATE_TASK',
        task: {
          '@id': 2,
          isAssigned: true,
          assignedTo: 'bob'
        }
      })
    ).toEqual({
      allTasks: [],
      unassignedTasks: [],
      taskLists: [
        { username: 'bob', items: [{
          '@id': 1,
          isAssigned: true,
          assignedTo: 'bob'
        }, {
          '@id': 2,
          isAssigned: true,
          assignedTo: 'bob'
        }] }
      ]
    })

  })

  it('should handle unassigned task', () => {

    expect(
      combinedTasks({
        allTasks: [],
        unassignedTasks: [],
        taskLists: [
          { username: 'bob', items: [{
            '@id': 1,
            isAssigned: true,
            assignedTo: 'bob'
          }] }
        ]
      }, {
        type: 'UPDATE_TASK',
        task: {
          '@id': 1,
          isAssigned: false,
          assignedTo: null
        }
      })
    ).toEqual({
      allTasks: [],
      unassignedTasks: [{
        '@id': 1,
        isAssigned: false,
        assignedTo: null
      }],
      taskLists: [
        { username: 'bob', items: [] }
      ]
    })

  })

  it('should handle unassigned task (not existing)', () => {

    expect(
      combinedTasks({
        allTasks: [],
        unassignedTasks: [],
        taskLists: [
          { username: 'bob', items: [] }
        ]
      }, {
        type: 'UPDATE_TASK',
        task: {
          '@id': 1,
          isAssigned: false,
          assignedTo: null
        }
      })
    ).toEqual({
      allTasks: [],
      unassignedTasks: [{
        '@id': 1,
        isAssigned: false,
        assignedTo: null
      }],
      taskLists: [
        { username: 'bob', items: [] }
      ]
    })

    expect(
      combinedTasks({
        allTasks: [],
        unassignedTasks: [{
          '@id': 1,
          isAssigned: false,
          assignedTo: null
        }],
        taskLists: [
          { username: 'bob', items: [] }
        ]
      }, {
        type: 'UPDATE_TASK',
        task: {
          '@id': 2,
          isAssigned: false,
          assignedTo: null
        }
      })
    ).toEqual({
      allTasks: [],
      unassignedTasks: [{
        '@id': 1,
        isAssigned: false,
        assignedTo: null
      }, {
        '@id': 2,
        isAssigned: false,
        assignedTo: null
      }],
      taskLists: [
        { username: 'bob', items: [] }
      ]
    })

  })

  it('should handle reassigned task', () => {

    expect(
      combinedTasks({
        allTasks: [],
        unassignedTasks: [],
        taskLists: [
          { username: 'bob', items: [{
            '@id': 1,
            isAssigned: true,
            assignedTo: 'bob'
          }] },
          { username: 'steve', items: [{
            '@id': 2,
            isAssigned: true,
            assignedTo: 'steve'
          }] }
        ]
      }, {
        type: 'UPDATE_TASK',
        task: {
          '@id': 2,
          isAssigned: true,
          assignedTo: 'bob'
        }
      })
    ).toEqual({
      allTasks: [],
      unassignedTasks: [],
      taskLists: [
        { username: 'bob', items: [{
          '@id': 1,
          isAssigned: true,
          assignedTo: 'bob'
        }, {
          '@id': 2,
          isAssigned: true,
          assignedTo: 'bob'
        }] },
        { username: 'steve', items: [] }
      ]
    })

  })

  it('should skip task out of range', () => {

    const date = moment('2019-11-20')

    expect(
      combinedTasks({
        date,
        allTasks: [],
        unassignedTasks: [],
        taskLists: []
      }, {
        type: 'ADD_CREATED_TASK',
        task: {
          '@id': 1,
          status: 'TODO',
          isAssigned: false,
          doneAfter: '2019-11-21 09:00:00',
          doneBefore: '2019-11-21 13:00:00',
        }
      })
    ).toEqual({
      date,
      allTasks: [],
      unassignedTasks: [],
      taskLists: []
    })

  })

  it('should handle task inside range', () => {

    const date = moment('2019-11-20')
    const task = {
      '@id': 1,
      status: 'TODO',
      isAssigned: false,
      doneAfter: '2019-11-19 09:00:00',
      doneBefore: '2019-11-21 19:00:00',
    }

    expect(
      combinedTasks({
        date,
        allTasks: [],
        unassignedTasks: [],
        taskLists: []
      }, {
        type: 'ADD_CREATED_TASK',
        task
      })
    ).toEqual({
      date,
      allTasks: [ task ],
      unassignedTasks: [ task ],
      taskLists: []
    })

  })

})
