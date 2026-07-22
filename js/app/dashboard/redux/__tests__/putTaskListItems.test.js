import moment from 'moment'

import { putTaskListItems } from '../actions'
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
