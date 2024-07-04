import {
  UPDATE_TOUR,
  DELETE_TOUR_SUCCESS,
  MODIFY_TOUR_REQUEST,
  MODIFY_TOUR_REQUEST_ERROR,
  MODIFY_TOUR_REQUEST_SUCCESS
} from './actions'
import { tourAdapter } from '../../coopcycle-frontend-js/logistics/redux'

const initialState = tourAdapter.getInitialState()

export default (state = initialState, action) => {

  switch (action.type) {
    case UPDATE_TOUR:
      return tourAdapter.upsertOne(state, action.tour)
    case MODIFY_TOUR_REQUEST:
      return tourAdapter.upsertOne(state, {...action.tour, items: action.items})
    case MODIFY_TOUR_REQUEST_SUCCESS:
      return tourAdapter.upsertOne(state, {...action.tour})
    case MODIFY_TOUR_REQUEST_ERROR:
      return tourAdapter.upsertOne(state, action.tour)
    case DELETE_TOUR_SUCCESS:
      return tourAdapter.removeOne(state, action.tour)

  }

  return state
}
