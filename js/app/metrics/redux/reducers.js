import {
  CHANGE_DATE_RANGE,
} from './actions'

export const initialState = {
  dateRange: '30d',
}

export default (state = initialState, action = {}) => {

  switch (action.type) {
  case CHANGE_DATE_RANGE:

    return {
      ...state,
      dateRange: action.payload,
    }
  }

  return state
}
