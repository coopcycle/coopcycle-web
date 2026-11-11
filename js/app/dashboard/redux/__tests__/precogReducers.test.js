import reducer, { initialState } from '../precogReducers'
import { precogUpdated, precogGone, precogSynced, precogExpired } from '../actions'

const ghost = (id, overrides = {}) => ({
  id,
  stage: 'checkout.open',
  rank: 2,
  precision: 800,
  latitude: 48.86,
  longitude: 2.35,
  vendor: 'Chez Max',
  slot: null,
  expiresAt: '2026-11-10T19:00:00+00:00',
  ...overrides,
})

describe('precogReducers', () => {

  it('adds a ghost it has not seen before', () => {
    const state = reducer(initialState, precogUpdated(ghost('abc')))

    expect(Object.keys(state)).toEqual(['abc'])
    expect(state.abc.rank).toBe(2)
  })

  it('replaces a ghost that moves up the funnel', () => {
    let state = reducer(initialState, precogUpdated(ghost('abc')))
    state = reducer(state, precogUpdated(ghost('abc', { rank: 4, stage: 'payment.open', precision: 250 })))

    expect(Object.keys(state)).toEqual(['abc'])
    expect(state.abc.rank).toBe(4)
    expect(state.abc.precision).toBe(250)
  })

  it('removes a ghost whose order has been placed', () => {
    let state = reducer(initialState, precogUpdated(ghost('abc')))
    state = reducer(state, precogGone({ id: 'abc' }))

    expect(state).toEqual({})
  })

  it('ignores a removal for a ghost it never had', () => {
    const state = reducer({ abc: ghost('abc') }, precogGone({ id: 'nope' }))

    expect(Object.keys(state)).toEqual(['abc'])
  })

  it('removes expired ghosts in one pass', () => {
    const state = reducer(
      { a: ghost('a'), b: ghost('b'), c: ghost('c') },
      precogExpired(['a', 'c'])
    )

    expect(Object.keys(state)).toEqual(['b'])
  })

  // A board that was disconnected missed both arrivals and departures, so a
  // merge would keep ghosts on the map that expired while it was away.
  it('replaces the whole set on a sync rather than merging', () => {
    const state = reducer(
      { stale: ghost('stale') },
      precogSynced([ghost('fresh')])
    )

    expect(Object.keys(state)).toEqual(['fresh'])
  })

  it('empties the board when a sync returns nothing', () => {
    expect(reducer({ stale: ghost('stale') }, precogSynced([]))).toEqual({})
  })

  it('leaves state untouched for unrelated actions', () => {
    const state = { abc: ghost('abc') }

    expect(reducer(state, { type: 'SOMETHING_ELSE' })).toBe(state)
  })
})
