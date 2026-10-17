import moment from 'moment'

import { addTaskListItems, removeTaskListItems } from '../actions'
import { storeFixture } from './storeFixture'
import { createStoreFromPreloadedState } from '../store'
import { selectTaskById, selectTaskListByUsername } from '../../../../shared/src/logistics/redux/selectors'
import { createClient } from '../../utils/client'

jest.mock('../../utils/client')
jest.mock('react-toastify', () => ({
  toast: { error: jest.fn(), warn: jest.fn() },
}))

const TASK_LIST = '/api/task_lists/112'
// '/api/tours/111' is the only item on admin's list in the fixture
const TOUR = '/api/tours/111'
// task 728 is unassigned in the fixture
const UNASSIGNED_TASK = '/api/tasks/728'

const selectTaskListItems = store =>
  selectTaskListByUsername(store.getState(), { username: 'admin' }).items

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

describe('addTaskListItems', () => {

  it('names only the items it is adding, never the rest of the list', async () => {
    const store = createStore()
    const requests = []

    mockRequest((config) => {
      requests.push(config)

      return Promise.resolve({
        data: { '@id': TASK_LIST, username: 'admin', items: [ TOUR, UNASSIGNED_TASK ] },
      })
    })

    await store.dispatch(addTaskListItems('admin', [ { '@id': UNASSIGNED_TASK } ]))

    expect(requests).toHaveLength(1)
    expect(requests[0]).toMatchObject({
      method: 'post',
      url: `${TASK_LIST}/items`,
      data: { items: [ UNASSIGNED_TASK ], position: null },
    })
    // The whole point: the tour that was already there is not mentioned, so
    // a board that had not heard of it could not have dropped it.
    expect(requests[0].data.items).not.toContain(TOUR)
  })

  it('sends the position it was dropped at', async () => {
    const store = createStore()
    const requests = []

    mockRequest((config) => {
      requests.push(config)

      return Promise.resolve({
        data: { '@id': TASK_LIST, username: 'admin', items: [ UNASSIGNED_TASK, TOUR ] },
      })
    })

    await store.dispatch(addTaskListItems('admin', [ { '@id': UNASSIGNED_TASK } ], 0))

    expect(requests[0].data).toMatchObject({ items: [ UNASSIGNED_TASK ], position: 0 })
  })

  it('shows the change before the API answers, in the place it will land', () => {
    const store = createStore()

    mockRequest(() => new Promise(() => {}))

    store.dispatch(addTaskListItems('admin', [ { '@id': UNASSIGNED_TASK } ], 0))

    expect(selectTaskListItems(store)).toEqual([ UNASSIGNED_TASK, TOUR ])
    expect(selectTaskById(store.getState(), UNASSIGNED_TASK)).toMatchObject({
      isAssigned: true,
      assignedTo: 'admin',
    })
  })

  it('rolls back the optimistic update when the API refuses', async () => {
    const store = createStore()

    // eslint-disable-next-line no-console
    const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {})

    mockRequest(() => Promise.reject(new Error('Internal Server Error')))

    await store.dispatch(addTaskListItems('admin', [ { '@id': UNASSIGNED_TASK } ]))

    expect(selectTaskListItems(store)).toEqual([ TOUR ])
    expect(selectTaskById(store.getState(), UNASSIGNED_TASK)).toMatchObject({
      isAssigned: false,
      assignedTo: null,
    })
    expect(store.getState().logistics.ui.taskListsLoading).toBe(false)

    consoleError.mockRestore()
  })
})

describe('removeTaskListItems', () => {

  it('deletes each item by name, then reads the list back', async () => {
    const store = createStore()
    const requests = []

    mockRequest((config) => {
      requests.push(config)

      if (config.method === 'get') {
        return Promise.resolve({
          data: { '@id': TASK_LIST, username: 'admin', items: [] },
        })
      }

      return Promise.resolve({ status: 204 })
    })

    await store.dispatch(removeTaskListItems('admin', [ { '@id': TOUR } ]))

    expect(requests).toEqual([
      expect.objectContaining({ method: 'delete', url: `${TASK_LIST}/items/tours/111` }),
      expect.objectContaining({ method: 'get', url: TASK_LIST }),
    ])
    expect(selectTaskListItems(store)).toEqual([])
  })

  it('unassigns optimistically, tours bringing their tasks along', () => {
    const store = createStore()

    mockRequest(() => new Promise(() => {}))

    store.dispatch(removeTaskListItems('admin', [ { '@id': TOUR } ]))

    expect(selectTaskListItems(store)).toEqual([])
    expect(selectTaskById(store.getState(), '/api/tasks/730')).toMatchObject({
      isAssigned: false,
      assignedTo: null,
    })
  })

  it('rolls back when a delete fails', async () => {
    const store = createStore()

    // eslint-disable-next-line no-console
    const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {})

    mockRequest(() => Promise.reject(new Error('Internal Server Error')))

    await store.dispatch(removeTaskListItems('admin', [ { '@id': TOUR } ]))

    expect(selectTaskListItems(store)).toEqual([ TOUR ])
    expect(selectTaskById(store.getState(), '/api/tasks/730')).toMatchObject({
      isAssigned: true,
      assignedTo: 'admin',
    })
    expect(store.getState().logistics.ui.taskListsLoading).toBe(false)

    consoleError.mockRestore()
  })
})
