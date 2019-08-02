import { combinedTasks, unassignedTasks, taskLists } from '../redux/reducers'

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

})
