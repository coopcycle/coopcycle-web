import moment from 'moment'
import { toast } from 'react-toastify'

import { moveTourToDay } from '../actions'
import { storeFixture } from './storeFixture'
import { createStoreFromPreloadedState } from '../store'
import { createClient } from '../../utils/client'

jest.mock('../../utils/client')
jest.mock('react-toastify', () => ({
  toast: { error: jest.fn(), warn: jest.fn() },
}))

const TOUR = '/api/tours/111'

const createStore = (tourOverrides = {}) => createStoreFromPreloadedState({
  ...storeFixture,
  logistics: {
    ...storeFixture.logistics,
    date: moment(storeFixture.logistics.date),
    // `toggleTourLoading` spreads this, and the fixture does not carry it.
    ui: {
      ...storeFixture.logistics.ui,
      loadingTourPanelsIds: [],
    },
    entities: {
      ...storeFixture.logistics.entities,
      tours: {
        ...storeFixture.logistics.entities.tours,
        entities: {
          ...storeFixture.logistics.entities.tours.entities,
          [TOUR]: {
            ...storeFixture.logistics.entities.tours.entities[TOUR],
            ...tourOverrides,
          },
        },
      },
    },
  },
})

const mockRequest = (request) => {
  createClient.mockReturnValue({ request })
}

const tourOf = store => store.getState().logistics.entities.tours.entities[TOUR]

const conflict = () => {
  const error = new Error('Conflict')
  error.response = { status: 409 }

  return error
}

const notFound = () => {
  const error = new Error('Not Found')
  error.response = { status: 404 }

  return error
}

beforeEach(() => {
  jest.clearAllMocks()
})

describe('moveTourToDay', () => {

  // Moving a tour re-sends its whole task list, so it is the same destructive
  // write `modifyTour` makes, and it needs the same precondition.
  it('tells the API which version it built the payload from', async () => {
    const store = createStore({ version: 4 })
    const requests = []

    mockRequest((config) => {
      requests.push(config)

      return Promise.resolve({ data: {} })
    })

    await store.dispatch(moveTourToDay(tourOf(store), '2024-01-10'))

    expect(requests).toHaveLength(1)
    expect(requests[0]).toMatchObject({
      method: 'put',
      url: TOUR,
      data: { date: '2024-01-10' },
    })
    expect(requests[0].headers['If-Match']).toBe('"4"')
  })

  // An instance that predates tour versioning serves no version at all.
  // Sending If-Match: "undefined" there would fail every move.
  it('sends no precondition when the tour carries no version', async () => {
    const store = createStore()
    const requests = []

    mockRequest((config) => {
      requests.push(config)

      return Promise.resolve({ data: {} })
    })

    await store.dispatch(moveTourToDay(tourOf(store), '2024-01-10'))

    expect(requests[0].headers['If-Match']).toBeUndefined()
  })

  it('takes the tour off the board once it has moved', async () => {
    const store = createStore({ version: 4 })

    mockRequest(() => Promise.resolve({ data: {} }))

    await store.dispatch(moveTourToDay(tourOf(store), '2024-01-10'))

    expect(tourOf(store)).toBeUndefined()
  })

  describe('when the tour changed under us', () => {

    it('leaves it where it is, and shows what it actually holds now', async () => {
      const store = createStore({ version: 4 })
      const requests = []

      mockRequest((config) => {
        requests.push(config)

        if (config.method === 'put') {
          return Promise.reject(conflict())
        }

        return Promise.resolve({
          data: { ...tourOf(store), version: 5, items: [ '/api/tasks/729' ] },
        })
      })

      await store.dispatch(moveTourToDay(tourOf(store), '2024-01-10'))

      // Nothing moved, so the tour is still on this day — and it now shows
      // the task list the other writer left, not the one we tried to carry.
      expect(tourOf(store)).toMatchObject({
        version: 5,
        items: [ '/api/tasks/729' ],
      })
      expect(requests.map(r => r.method)).toEqual([ 'put', 'get' ])
      expect(toast.error).toHaveBeenCalled()
    })

    it('takes it off the board when the write we lost to was a delete', async () => {
      const store = createStore({ version: 4 })

      mockRequest((config) => {
        if (config.method === 'put') {
          return Promise.reject(conflict())
        }

        return Promise.reject(notFound())
      })

      await store.dispatch(moveTourToDay(tourOf(store), '2024-01-10'))

      // Leaving it would strand it: every later write conflicts, and the
      // refetch that would clear it can never succeed.
      expect(tourOf(store)).toBeUndefined()
    })
  })
})
