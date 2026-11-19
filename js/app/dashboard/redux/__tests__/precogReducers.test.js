import reducer, { initialState } from '../precogReducers'
import {
  precogUpdated,
  precogGone,
  precogSynced,
  precogExpired,
  precogCompleted,
  precogCompletionEnded,
} from '../actions'

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

const withGhosts = ghosts => ({ ghosts, completions: {} })

describe('precogReducers', () => {

  it('adds a ghost it has not seen before', () => {
    const state = reducer(initialState, precogUpdated(ghost('abc')))

    expect(Object.keys(state.ghosts)).toEqual(['abc'])
    expect(state.ghosts.abc.rank).toBe(2)
  })

  it('replaces a ghost that moves up the funnel', () => {
    let state = reducer(initialState, precogUpdated(ghost('abc')))
    state = reducer(state, precogUpdated(ghost('abc', { rank: 4, stage: 'payment.open', precision: 250 })))

    expect(Object.keys(state.ghosts)).toEqual(['abc'])
    expect(state.ghosts.abc.rank).toBe(4)
    expect(state.ghosts.abc.precision).toBe(250)
  })

  it('removes a ghost whose order has been placed', () => {
    let state = reducer(initialState, precogUpdated(ghost('abc')))
    state = reducer(state, precogGone({ id: 'abc' }))

    expect(state.ghosts).toEqual({})
  })

  it('ignores a removal for a ghost it never had', () => {
    const state = reducer(withGhosts({ abc: ghost('abc') }), precogGone({ id: 'nope' }))

    expect(Object.keys(state.ghosts)).toEqual(['abc'])
  })

  it('removes expired ghosts in one pass', () => {
    const state = reducer(
      withGhosts({ a: ghost('a'), b: ghost('b'), c: ghost('c') }),
      precogExpired(['a', 'c'])
    )

    expect(Object.keys(state.ghosts)).toEqual(['b'])
  })

  // A board that was disconnected missed both arrivals and departures, so a
  // merge would keep ghosts on the map that expired while it was away.
  it('replaces the whole set on a sync rather than merging', () => {
    const state = reducer(
      withGhosts({ stale: ghost('stale') }),
      precogSynced([ghost('fresh')])
    )

    expect(Object.keys(state.ghosts)).toEqual(['fresh'])
  })

  it('empties the board when a sync returns nothing', () => {
    expect(reducer(withGhosts({ stale: ghost('stale') }), precogSynced([])).ghosts).toEqual({})
  })

  // The halo and the mark must never be on the map at the same time: that would
  // read as two orders at one address.
  it('swaps the ghost for a completion in one step', () => {
    let state = reducer(initialState, precogUpdated(ghost('abc')))
    state = reducer(state, precogCompleted({ id: 'abc', latitude: 48.86, longitude: 2.35 }))

    expect(state.ghosts).toEqual({})
    expect(Object.keys(state.completions)).toEqual(['abc'])
    expect(state.completions.abc.latitude).toBe(48.86)
  })

  it('drops the completion once its moment has passed', () => {
    let state = reducer(initialState, precogUpdated(ghost('abc')))
    state = reducer(state, precogCompleted({ id: 'abc', latitude: 48.86, longitude: 2.35 }))
    state = reducer(state, precogCompletionEnded('abc'))

    expect(state.completions).toEqual({})
  })

  // A sync replaces the carts the server knows about. Completions are not
  // carts and the server does not track them, so a sync landing mid-animation
  // must not wipe the mark.
  it('leaves completions alone on a sync', () => {
    let state = reducer(initialState, precogUpdated(ghost('abc')))
    state = reducer(state, precogCompleted({ id: 'abc', latitude: 48.86, longitude: 2.35 }))
    state = reducer(state, precogSynced([ghost('other')]))

    expect(Object.keys(state.ghosts)).toEqual(['other'])
    expect(Object.keys(state.completions)).toEqual(['abc'])
  })

  it('leaves state untouched for unrelated actions', () => {
    const state = withGhosts({ abc: ghost('abc') })

    expect(reducer(state, { type: 'SOMETHING_ELSE' })).toBe(state)
  })
})
