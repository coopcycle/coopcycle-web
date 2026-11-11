import { precogUpdated, precogGone, precogSynced, precogExpired } from './actions'

/**
 * Carts that are on their way to becoming orders, keyed by the opaque id the
 * server assigns them.
 *
 * Deliberately a plain map and not an entity adapter: a ghost has no identity
 * beyond that id, nothing sorts or selects it, and it is replaced wholesale on
 * every update rather than patched.
 */
export const initialState = {}

const without = (state, ids) => {
  const next = { ...state }
  ids.forEach(id => delete next[id])
  return next
}

export default (state = initialState, action) => {
  switch (action.type) {

  case precogUpdated.type:
    return {
      ...state,
      [action.payload.id]: action.payload,
    }

  case precogGone.type:
    return without(state, [ action.payload.id ])

  case precogExpired.type:
    return without(state, action.payload)

  case precogSynced.type:
    // A replacement, not a merge: anything the server no longer lists has
    // expired, and merging would keep it on the map forever.
    return Object.fromEntries(action.payload.map(ghost => [ ghost.id, ghost ]))
  }

  return state
}
