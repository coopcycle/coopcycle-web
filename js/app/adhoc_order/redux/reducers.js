import {
  CREATE_ADHOC_ORDER_REQUEST,
  CREATE_ADHOC_ORDER_REQUEST_FAILURE,
  CREATE_ADHOC_ORDER_REQUEST_SUCCESS,
  REFRESH_TOKEN_SUCCESS,
} from "./actions"


export const initialState = {
  jwt: '',
  restaurant: null,
  taxCategories: [],
  isFetching: false,
  order: null,
}

export default (state = initialState, action = {}) => {
  switch (action.type) {
    case REFRESH_TOKEN_SUCCESS:
      return {
        ...state,
        jwt: action.payload
      }

    case CREATE_ADHOC_ORDER_REQUEST:
      return {
        ...state,
        isFetching: true
      }

    case CREATE_ADHOC_ORDER_REQUEST_SUCCESS:
      return {
        ...state,
        isFetching: false,
        order: action.payload
      }

    case CREATE_ADHOC_ORDER_REQUEST_FAILURE:
      return {
        ...state,
        isFetching: false,
        error: action.payload
      }

    default:
      return state
  }
}