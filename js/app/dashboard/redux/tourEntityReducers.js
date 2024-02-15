import {
  UPDATE_TOUR,
  DELETE_TOUR_SUCCESS,
  MODIFY_TOUR_REQUEST,
  MODIFY_TOUR_REQUEST_ERROR
} from './actions'
import { tourAdapter } from '../../coopcycle-frontend-js/logistics/redux'

const initialState = tourAdapter.getInitialState()

export default (state = initialState, action) => {


  switch (action.type) {
    case UPDATE_TOUR:
      return tourAdapter.upsertOne(state, action.tour)

    case MODIFY_TOUR_REQUEST:
      const _tour = {...action.tour, items: action.tasks}
      _tour.itemIds = _tour.items.map(item => item['@id'])
      return tourAdapter.upsertOne(state, _tour)
    case MODIFY_TOUR_REQUEST_ERROR:
      return tourAdapter.upsertOne(state, action.tour)

    case DELETE_TOUR_SUCCESS:
      return tourAdapter.removeOne(state, action.tour)
  
  }

  return state
}
