import {
  UPDATE_TOUR,
} from './actions'
import { tourAdapter } from '../../coopcycle-frontend-js/logistics/redux'

const initialState = tourAdapter.getInitialState()

export default (state = initialState, action) => {

  switch (action.type) {
    case UPDATE_TOUR:
      return tourAdapter.upsertOne(state, action.tour)
  }

  return state
}
