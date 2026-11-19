import { handlePrecogGone } from '../middlewares'
import { precogGone, precogCompleted, precogCompletionEnded } from '../actions'

const ghost = {
  id: 'abc',
  stage: 'payment.submitted',
  rank: 5,
  precision: 75,
  latitude: 48.8586,
  longitude: 2.3470,
  expiresAt: '2026-11-16T19:00:00+00:00',
}

const stateWith = ghosts => () => ({ precog: { ghosts, completions: {} } })

describe('handlePrecogGone', () => {

  beforeEach(() => jest.useFakeTimers())
  afterEach(() => jest.useRealTimers())

  // The whole point of the reason field: a sale and a lost cart are the same
  // disappearance, and only one of them is worth marking.
  it('marks the spot when the cart was paid for', () => {
    const dispatch = jest.fn()

    handlePrecogGone(dispatch, stateWith({ abc: ghost }), { id: 'abc', reason: 'order_placed' })

    expect(dispatch).toHaveBeenCalledTimes(1)
    expect(dispatch).toHaveBeenCalledWith(precogCompleted({
      id: 'abc',
      latitude: 48.8586,
      longitude: 2.3470,
    }))
  })

  it('clears the mark once its moment has passed', () => {
    const dispatch = jest.fn()

    handlePrecogGone(dispatch, stateWith({ abc: ghost }), { id: 'abc', reason: 'order_placed' })
    jest.runAllTimers()

    expect(dispatch).toHaveBeenLastCalledWith(precogCompletionEnded('abc'))
  })

  it('removes a ghost that left for any other reason without marking it', () => {
    const dispatch = jest.fn()

    handlePrecogGone(dispatch, stateWith({ abc: ghost }), { id: 'abc', reason: 'something_else' })

    expect(dispatch).toHaveBeenCalledTimes(1)
    expect(dispatch).toHaveBeenCalledWith(precogGone({ id: 'abc', reason: 'something_else' }))
  })

  // An older server, or a replayed event, carries no reason at all.
  it('removes a ghost when no reason is given', () => {
    const dispatch = jest.fn()

    handlePrecogGone(dispatch, stateWith({ abc: ghost }), { id: 'abc' })

    expect(dispatch).toHaveBeenCalledWith(precogGone({ id: 'abc' }))
  })

  // Marking a spot for a ghost this board never had would mean inventing the
  // position: a board that joined late gets nothing rather than a wrong dot.
  it('marks nothing for a ghost it never had', () => {
    const dispatch = jest.fn()

    handlePrecogGone(dispatch, stateWith({}), { id: 'never-seen', reason: 'order_placed' })

    expect(dispatch).toHaveBeenCalledTimes(1)
    expect(dispatch).toHaveBeenCalledWith(precogGone({ id: 'never-seen', reason: 'order_placed' }))
  })
})
