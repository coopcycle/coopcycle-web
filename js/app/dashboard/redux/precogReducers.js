import {
  precogUpdated,
  precogGone,
  precogSynced,
  precogExpired,
  precogCompleted,
  precogCompletionEnded,
} from './actions'

/**
 * Two sets, because a halo and its disappearance are different things.
 *
 * `ghosts` are carts still moving through checkout, keyed by the opaque id the
 * server assigns them. Deliberately a plain map and not an entity adapter: a
 * ghost has no identity beyond that id, nothing sorts or selects it, and it is
 * replaced wholesale on every update rather than patched.
 *
 * `completions` are the two and a half seconds after a cart was paid for. The
 * ghost is gone by then, but the board still needs its position to mark the
 * spot, so it moves here rather than simply being deleted.
 */
export const initialState = {
  ghosts: {},
  completions: {},
}

const without = (map, ids) => {
  const next = { ...map }
  ids.forEach(id => delete next[id])
  return next
}

export default (state = initialState, action) => {
  switch (action.type) {

  case precogUpdated.type:
    return {
      ...state,
      ghosts: { ...state.ghosts, [action.payload.id]: action.payload },
    }

  case precogGone.type:
    return { ...state, ghosts: without(state.ghosts, [ action.payload.id ]) }

  case precogExpired.type:
    return { ...state, ghosts: without(state.ghosts, action.payload) }

  case precogSynced.type:
    // A replacement, not a merge: anything the server no longer lists has
    // expired, and merging would keep it on the map forever. Completions are
    // left alone — they are not carts and the server does not track them.
    return {
      ...state,
      ghosts: Object.fromEntries(action.payload.map(ghost => [ ghost.id, ghost ])),
    }

  case precogCompleted.type:
    // The ghost leaves and the mark takes its place in one step, so the board
    // never shows both at once.
    return {
      ghosts: without(state.ghosts, [ action.payload.id ]),
      completions: { ...state.completions, [action.payload.id]: action.payload },
    }

  case precogCompletionEnded.type:
    return { ...state, completions: without(state.completions, [ action.payload ]) }
  }

  return state
}
