import reducers from '../redux/dispatchReducers'
import moment from 'moment'

describe('combinedTasks reducer', () => {

  it('should handle assigned task', () => {

    expect(
      reducers({
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
    ).toMatchObject({
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
      reducers({
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
    ).toMatchObject({
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
      reducers({
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
    ).toMatchObject({
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
      reducers({
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
    ).toMatchObject({
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
      reducers({
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
    ).toMatchObject({
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
      reducers({
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
    ).toMatchObject({
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
      reducers({
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
    ).toMatchObject({
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
      reducers({
        date,
        unassignedTasks: [],
        taskLists: []
      }, {
        type: 'UPDATE_TASK',
        task: {
          '@id': 1,
          status: 'TODO',
          isAssigned: false,
          doneAfter: '2019-11-21 09:00:00',
          doneBefore: '2019-11-21 13:00:00',
        }
      })
    ).toMatchObject({
      date,
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
      reducers({
        date,
        unassignedTasks: [],
        taskLists: []
      }, {
        type: 'UPDATE_TASK',
        task
      })
    ).toMatchObject({
      date,
      unassignedTasks: [ task ],
      taskLists: []
    })

  })

  it('should handle new task already assigned', () => {

    const date = moment('2019-11-20')
    const task = {
      '@id': 1,
      status: 'TODO',
      isAssigned: true,
      assignedTo: 'bob',
      doneAfter: '2019-11-19 09:00:00',
      doneBefore: '2019-11-21 19:00:00',
    }

    expect(
      reducers({
        date,
        unassignedTasks: [],
        taskLists: []
      }, {
        type: 'UPDATE_TASK',
        task
      })
    ).toMatchObject({
      date,
      unassignedTasks: [],
      taskLists: [{
        '@context': '/api/contexts/TaskList',
        '@id': null,
        '@type': 'TaskList',
        distance: 0,
        duration: 0,
        polyline: '',
        createdAt: expect.any(String),
        updatedAt: expect.any(String),
        username: 'bob',
        items:[ task ],
      }]
    })

  })

})
