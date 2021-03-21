import _ from 'lodash'
import { moment } from '../../coopcycle-frontend-js'

import {
  SET_GEOLOCATION,
  SCAN_POSITIONS,
} from './actions'

import { isOffline } from './utils'

const initialState = {
  positions: [],
  offline: [],
}

export default (state = initialState, action) => {
  switch (action.type) {
  case SET_GEOLOCATION:

    const marker = {
      username: action.username,
      coords: action.coords,
      lastSeen: moment(action.timestamp, 'X'),
    }

    const newPositions = state.positions.slice(0)
    const index = _.findIndex(newPositions, position => position.username === action.username)
    if (-1 !== index) {
      newPositions.splice(index, 1, marker)
    } else {
      newPositions.push(marker)
    }

    return {
      ...state,
      positions: newPositions,
    }

  case SCAN_POSITIONS:

    const offline = _.reduce(state.positions, (acc, position) => {

      if (isOffline(position.lastSeen)) {
        acc.push(position.username)
      }

      return acc
    }, [])

    if (!_.isEqual(offline, state.offline)) {
      return {
        ...state,
        offline,
      }
    }

    break
  }

  return state
}
