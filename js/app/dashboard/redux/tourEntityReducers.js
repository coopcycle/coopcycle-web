import {
  UPDATE_TOUR,
  DELETE_TOUR_SUCCESS
} from './actions'
import { tourAdapter } from '../../coopcycle-frontend-js/logistics/redux'

const initialState = tourAdapter.getInitialState()

export default (state = initialState, action) => {

  switch (action.type) {
    case UPDATE_TOUR:
      return tourAdapter.upsertOne(state, action.tour)
    
    case DELETE_TOUR_SUCCESS:
      return tourAdapter.removeOne(state, action.tour)
  
  }

  return state
}
