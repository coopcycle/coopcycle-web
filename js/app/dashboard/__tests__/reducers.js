import { unassignedTasks, taskLists } from '../store/reducers'

describe('taskLists reducer', () => {

  it('should handle UPDATE_TASK', () => {

    expect(
      taskLists([], {
        type: 'UPDATE_TASK',
        task: {
          '@id': 1,
          isAssigned: true,
          assignedTo: 'bob'
        }
      })
    ).toEqual([])

    expect(
      taskLists([
        {
          username: 'bob',
          items: []
        },
        {
          username: 'sarah',
          items: []
        }
      ], {
        type: 'UPDATE_TASK',
        task: {
          '@id': 1,
          isAssigned: true,
          assignedTo: 'bob'
        }
      })
    ).toEqual([
      {
        username: 'bob',
        items: [{
          '@id': 1,
          isAssigned: true,
          assignedTo: 'bob'
        }]
      },
      {
        username: 'sarah',
        items: []
      }
    ])

    expect(
      taskLists([
        {
          username: 'bob',
          items: [{ '@id': 1 }, { '@id': 2 }]
        },
        {
          username: 'sarah',
          items: []
        }
      ], {
        type: 'UPDATE_TASK',
        task: {
          '@id': 1,
          isAssigned: true,
          assignedTo: 'bob'
        }
      })
    ).toEqual([
      {
        username: 'bob',
        items: [
          {
            '@id': 1,
            isAssigned: true,
            assignedTo: 'bob'
          },
          { '@id': 2 }
        ]
      },
      {
        username: 'sarah',
        items: []
      }
    ])

    expect(
      taskLists([
        {
          username: 'bob',
          items: [{ '@id': 1 }, { '@id': 2 }]
        },
        {
          username: 'sarah',
          items: []
        }
      ], {
        type: 'UPDATE_TASK',
        task: {
          '@id': 1,
          isAssigned: true,
          assignedTo: 'sarah'
        }
      })
    ).toEqual([
      {
        username: 'bob',
        items: [
          { '@id': 2 }
        ]
      },
      {
        username: 'sarah',
        items: [
          {
            '@id': 1,
            isAssigned: true,
            assignedTo: 'sarah'
          },
        ]
      }
    ])

  })

})

describe('unassignedTasks reducer', () => {

  it('should handle UPDATE_TASK', () => {

    expect(
      unassignedTasks([], {
        type: 'UPDATE_TASK',
        task: {
          '@id': 1
        }
      })
    ).toEqual([
      {
        '@id': 1
      }
    ])

    expect(
      unassignedTasks([
        { '@id': 1 }
      ], {
        type: 'UPDATE_TASK',
        task: { '@id': 2 }
      })
    ).toEqual([
      { '@id': 1 },
      { '@id': 2 }
    ])

    expect(
      unassignedTasks([
        { '@id': 1 },
        { '@id': 2 }
      ], {
        type: 'UPDATE_TASK',
        task: { '@id': 2, isAssigned: true, assignedTo: 'bob' }
      })
    ).toEqual([
      { '@id': 1 }
    ])

    expect(
      unassignedTasks([
        { '@id': 1 },
        { '@id': 2 }
      ], {
        type: 'UPDATE_TASK',
        task: { '@id': 3, isAssigned: true, assignedTo: 'bob' }
      })
    ).toEqual([
      { '@id': 1 },
      { '@id': 2 }
    ])

    expect(
      unassignedTasks([
        { '@id': 1 },
        { '@id': 2 }
      ], {
        type: 'UPDATE_TASK',
        task: { '@id': 3, isAssigned: false }
      })
    ).toEqual([
      { '@id': 1 },
      { '@id': 2 },
      { '@id': 3, isAssigned: false }
    ])

  })

})
