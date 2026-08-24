import moment from 'moment'

import { putTaskListItems, removePreviouslyAssignedTasks, taskListsUpdated } from '../actions'
import { storeFixture } from './storeFixture'
import { createStoreFromPreloadedState } from '../store'
import { selectTaskById, selectTaskListByUsername } from '../../../../shared/src/logistics/redux/selectors'
import { createClient } from '../../utils/client'

jest.mock('../../utils/client')
jest.mock('react-toastify', () => ({
  toast: { error: jest.fn(), warn: jest.fn() },
}))

// '/api/tours/111' contains tasks 729, 730, 731 & 727
const TOUR = '/api/tours/111'
// task 728 is unassigned in the fixture
const UNASSIGNED_TASK = '/api/tasks/728'

const selectTaskListItems = store =>
  selectTaskListByUsername(store.getState(), { username: 'admin' }).items

// the fixture stores the date as a string, the actions expect a moment object
const createStore = () => createStoreFromPreloadedState({
  ...storeFixture,
  logistics: {
    ...storeFixture.logistics,
    date: moment(storeFixture.logistics.date),
  },
})

const mockRequest = (request) => {
  createClient.mockReturnValue({ request })
}

beforeAll(() => {
  window.Routing = { generate: () => '/api/task_lists/set_items/2024-01-09/admin' }
})

describe('putTaskListItems', () => {

  it('assigns the tasks optimistically, before the API responds', () => {
    const store = createStore()

    // never resolves, so we only observe the optimistic update
    mockRequest(() => new Promise(() => {}))

    expect(selectTaskById(store.getState(), UNASSIGNED_TASK)).toMatchObject({
      isAssigned: false,
      assignedTo: null,
    })

    store.dispatch(putTaskListItems('admin', [ TOUR, UNASSIGNED_TASK ]))

    expect(selectTaskListItems(store)).toEqual([ TOUR, UNASSIGNED_TASK ])
    expect(selectTaskById(store.getState(), UNASSIGNED_TASK)).toMatchObject({
      isAssigned: true,
      assignedTo: 'admin',
    })
  })

  it('unassigns optimistically the tasks removed from the task list, tours included', () => {
    const store = createStore()

    mockRequest(() => new Promise(() => {}))

    store.dispatch(putTaskListItems('admin', []))

    expect(selectTaskListItems(store)).toEqual([])
    // tasks of the tour that was removed are unassigned as well
    expect(selectTaskById(store.getState(), '/api/tasks/730')).toMatchObject({
      isAssigned: false,
      assignedTo: null,
    })
  })

  it('rolls back the optimistic update when the API call fails', async () => {
    const store = createStore()

    // eslint-disable-next-line no-console
    const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {})

    mockRequest(() => Promise.reject(new Error('Internal Server Error')))

    await store.dispatch(putTaskListItems('admin', [ TOUR, UNASSIGNED_TASK ]))

    expect(selectTaskListItems(store)).toEqual([ TOUR ])
    expect(selectTaskById(store.getState(), UNASSIGNED_TASK)).toMatchObject({
      isAssigned: false,
      assignedTo: null,
    })
    // the task list must not stay stuck in a loading state, it disables drag'n'drop
    expect(store.getState().logistics.ui.taskListsLoading).toBe(false)

    consoleError.mockRestore()
  })

  it('keeps the API response when the call succeeds', async () => {
    const store = createStore()

    mockRequest(() => Promise.resolve({
      data: {
        '@id': '/api/task_lists/112',
        username: 'admin',
        items: [ TOUR, UNASSIGNED_TASK ],
      },
    }))

    await store.dispatch(putTaskListItems('admin', [ TOUR, UNASSIGNED_TASK ]))

    expect(selectTaskListItems(store)).toEqual([ TOUR, UNASSIGNED_TASK ])
    expect(store.getState().logistics.ui.taskListsLoading).toBe(false)
  })
})

const deferred = () => {
  let resolve, reject
  const promise = new Promise((res, rej) => { resolve = res; reject = rej })
  return { promise, resolve, reject }
}

const taskListResponse = items => ({
  data: {
    '@id': '/api/task_lists/112',
    username: 'admin',
    items,
    updatedAt: '2024-01-09T11:02:30+01:00',
  },
})

describe('putTaskListItems, concurrent modifications', () => {

  it('discards a response that has been superseded by a newer modification', async () => {
    const store = createStore()

    const first = deferred()
    const second = deferred()
    const requests = [ first, second ]
    mockRequest(() => requests.shift().promise)

    const firstCall = store.dispatch(putTaskListItems('admin', [ TOUR ]))
    const secondCall = store.dispatch(putTaskListItems('admin', [ TOUR, UNASSIGNED_TASK ]))

    // the responses come back in the reverse order
    second.resolve(taskListResponse([ TOUR, UNASSIGNED_TASK ]))
    await secondCall
    first.resolve(taskListResponse([ TOUR ]))
    await firstCall

    // the outdated response must not have reverted the latest modification
    expect(selectTaskListItems(store)).toEqual([ TOUR, UNASSIGNED_TASK ])
    expect(store.getState().logistics.ui.taskListsRequests.admin.pending).toBe(0)
  })

  it('keeps the task lists loading as long as a modification is in flight', async () => {
    const store = createStore()

    const first = deferred()
    const second = deferred()
    const requests = [ first, second ]
    mockRequest(() => requests.shift().promise)

    const firstCall = store.dispatch(putTaskListItems('admin', [ TOUR ]))
    const secondCall = store.dispatch(putTaskListItems('admin', [ TOUR, UNASSIGNED_TASK ]))

    second.resolve(taskListResponse([ TOUR, UNASSIGNED_TASK ]))
    await secondCall

    // drag'n'drop must stay disabled, the first modification is still in flight
    expect(store.getState().logistics.ui.taskListsLoading).toBe(true)

    first.resolve(taskListResponse([ TOUR ]))
    await firstCall

    expect(store.getState().logistics.ui.taskListsLoading).toBe(false)
  })

  it('does not roll back a superseded modification that failed', async () => {
    const store = createStore()

    // eslint-disable-next-line no-console
    const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {})

    const first = deferred()
    const second = deferred()
    const requests = [ first, second ]
    mockRequest(() => requests.shift().promise)

    const firstCall = store.dispatch(putTaskListItems('admin', [ TOUR ]))
    const secondCall = store.dispatch(putTaskListItems('admin', [ TOUR, UNASSIGNED_TASK ]))

    second.resolve(taskListResponse([ TOUR, UNASSIGNED_TASK ]))
    await secondCall
    first.reject(new Error('Internal Server Error'))
    await firstCall

    expect(selectTaskListItems(store)).toEqual([ TOUR, UNASSIGNED_TASK ])

    consoleError.mockRestore()
  })
})

describe('taskListsUpdated', () => {

  it('ignores an event received while we are modifying the task list', () => {
    const store = createStore()

    // never resolves, the modification stays in flight
    mockRequest(() => new Promise(() => {}))
    store.dispatch(putTaskListItems('admin', [ TOUR, UNASSIGNED_TASK ]))

    store.dispatch(taskListsUpdated({
      '@id': '/api/task_lists/112',
      username: 'admin',
      items: [],
      updatedAt: '2024-01-09T11:02:30+01:00',
    }))

    expect(selectTaskListItems(store)).toEqual([ TOUR, UNASSIGNED_TASK ])
  })

  it('ignores an event older than the task list we already hold', () => {
    const store = createStore()

    store.dispatch(taskListsUpdated({
      '@id': '/api/task_lists/112',
      username: 'admin',
      items: [],
      // the fixture task list was updated at 11:01:58
      updatedAt: '2024-01-09T10:00:00+01:00',
    }))

    expect(selectTaskListItems(store)).toEqual([ TOUR ])
  })

  it('applies an event newer than the task list we already hold', () => {
    const store = createStore()

    store.dispatch(taskListsUpdated({
      '@id': '/api/task_lists/112',
      username: 'admin',
      items: [ TOUR, UNASSIGNED_TASK ],
      updatedAt: '2024-01-09T12:00:00+01:00',
    }))

    expect(selectTaskListItems(store)).toEqual([ TOUR, UNASSIGNED_TASK ])
  })
})

// task 728 assigned to a second rider, so we can move it over to admin
const createStoreWithTwoRiders = () => {
  const { logistics } = storeFixture

  return createStoreFromPreloadedState({
    ...storeFixture,
    logistics: {
      ...logistics,
      date: moment(logistics.date),
      entities: {
        ...logistics.entities,
        tasks: {
          ...logistics.entities.tasks,
          entities: {
            ...logistics.entities.tasks.entities,
            [UNASSIGNED_TASK]: {
              ...logistics.entities.tasks.entities[UNASSIGNED_TASK],
              isAssigned: true,
              assignedTo: 'bob',
            },
          },
        },
        taskLists: {
          ids: [ ...logistics.entities.taskLists.ids, 'bob' ],
          entities: {
            ...logistics.entities.taskLists.entities,
            bob: {
              '@id': '/api/task_lists/113',
              '@type': 'TaskList',
              username: 'bob',
              date: '2024-01-09',
              updatedAt: '2024-01-09T11:01:58+01:00',
              items: [ UNASSIGNED_TASK ],
            },
          },
        },
      },
    },
  })
}

const itemsOf = (store, username) =>
  selectTaskListByUsername(store.getState(), { username }).items

// what handleDropInTaskList() does when tasks are moved from a rider to another
const moveTaskToAdmin = (store, task) => {
  const relatedChanges = store.dispatch(removePreviouslyAssignedTasks('admin', [ task ]))
  return store.dispatch(putTaskListItems('admin', [ TOUR, task['@id'] ], relatedChanges))
}

describe('putTaskListItems, tasks moved between riders', () => {

  it('gives the tasks back to the previous rider when the call fails', async () => {
    const store = createStoreWithTwoRiders()

    // eslint-disable-next-line no-console
    const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {})

    mockRequest(() => Promise.reject(new Error('Internal Server Error')))

    await moveTaskToAdmin(store, { '@id': UNASSIGNED_TASK })

    expect(itemsOf(store, 'admin')).toEqual([ TOUR ])
    expect(itemsOf(store, 'bob')).toEqual([ UNASSIGNED_TASK ])
    // the task must not be left unassigned, nothing was ever persisted
    expect(selectTaskById(store.getState(), UNASSIGNED_TASK)).toMatchObject({
      isAssigned: true,
      assignedTo: 'bob',
    })

    consoleError.mockRestore()
  })

  it('keeps the move when the call succeeds', async () => {
    const store = createStoreWithTwoRiders()

    mockRequest(() => Promise.resolve(taskListResponse([ TOUR, UNASSIGNED_TASK ])))

    await moveTaskToAdmin(store, { '@id': UNASSIGNED_TASK })

    expect(itemsOf(store, 'admin')).toEqual([ TOUR, UNASSIGNED_TASK ])
    expect(itemsOf(store, 'bob')).toEqual([])
    expect(selectTaskById(store.getState(), UNASSIGNED_TASK)).toMatchObject({
      isAssigned: true,
      assignedTo: 'admin',
    })
  })

  it('does not give back tasks to a rider whose task list changed since', async () => {
    const store = createStoreWithTwoRiders()

    // eslint-disable-next-line no-console
    const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {})
    const consoleDebug = jest.spyOn(console, 'debug').mockImplementation(() => {})

    const failing = deferred()
    const responses = [
      failing.promise,
      // bob's own modification goes through
      Promise.resolve({
        data: {
          '@id': '/api/task_lists/113',
          username: 'bob',
          items: [ '/api/tasks/734' ],
          updatedAt: '2024-01-09T11:03:00+01:00',
        },
      }),
    ]
    mockRequest(() => responses.shift())

    const call = moveTaskToAdmin(store, { '@id': UNASSIGNED_TASK })

    // meanwhile, bob's task list is modified again
    await store.dispatch(putTaskListItems('bob', [ '/api/tasks/734' ]))

    failing.reject(new Error('Internal Server Error'))
    await call

    // bob's newer state wins, restoring would have discarded it
    expect(itemsOf(store, 'bob')).toEqual([ '/api/tasks/734' ])

    consoleError.mockRestore()
    consoleDebug.mockRestore()
  })
})
