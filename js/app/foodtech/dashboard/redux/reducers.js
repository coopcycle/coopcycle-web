import moment from 'moment'
import _ from 'lodash'

import {
  SET_CURRENT_ORDER,
  ORDER_CREATED,
  ORDER_ACCEPTED,
  ORDER_REFUSED,
  ORDER_CANCELLED,
  ORDER_FULFILLED,
  FETCH_REQUEST,
  ACCEPT_ORDER_REQUEST_SUCCESS,
  ACCEPT_ORDER_REQUEST_FAILURE,
  CANCEL_ORDER_REQUEST_SUCCESS,
  CANCEL_ORDER_REQUEST_FAILURE,
  REFUSE_ORDER_REQUEST_SUCCESS,
  REFUSE_ORDER_REQUEST_FAILURE,
  DELAY_ORDER_REQUEST_SUCCESS,
  DELAY_ORDER_REQUEST_FAILURE,
  SEARCH_RESULTS,
} from './actions'

export const initialState = {
  orders: [],
  order: null,
  date: moment().format('YYYY-MM-DD'),
  jwt: '',
  restaurant: null,
  isFetching: false,
  acceptOrderRoute: 'admin_order_accept',
  refuseOrderRoute: 'admin_order_refuse',
  delayOrderRoute: 'admin_order_delay',
  cancelOrderRoute: 'admin_order_cancel',
  currentRoute: 'admin_foodtech_dashboard',
  preparationDelay: 0,
  showSettings: true,
  showSearch: false,
  searchQuery: '',
  searchResults: [],
}

function replaceOrder(orders, order) {

  const orderIndex = _.findIndex(orders, o => o.id === order.id)
  if (-1 !== orderIndex) {
    const newOrders = orders.slice()
    newOrders.splice(orderIndex, 1, Object.assign({}, order))

    return newOrders
  }

  return orders
}

export default (state = initialState, action = {}) => {

  switch (action.type) {
  case FETCH_REQUEST:

    return {
      ...state,
      isFetching: true,
    }

  case ACCEPT_ORDER_REQUEST_FAILURE:
  case CANCEL_ORDER_REQUEST_FAILURE:
  case REFUSE_ORDER_REQUEST_FAILURE:
  case DELAY_ORDER_REQUEST_FAILURE:

    return {
      ...state,
      isFetching: false,
    }

  case ACCEPT_ORDER_REQUEST_SUCCESS:
  case REFUSE_ORDER_REQUEST_SUCCESS:
  case CANCEL_ORDER_REQUEST_SUCCESS:
  case DELAY_ORDER_REQUEST_SUCCESS:

    return {
      ...state,
      isFetching: false,
      orders: replaceOrder(state.orders, action.payload),
      order: null,
    }

  case ORDER_CREATED:

    // The order is not for the current date, skip
    if (moment(action.payload.shippedAt).format('YYYY-MM-DD') !== state.date) {

      return state
    }

    const newOrders = state.orders.slice()
    newOrders.push(action.payload)

    return {
      ...state,
      orders: newOrders,
    }

  case ORDER_ACCEPTED:

    return {
      ...state,
      orders: replaceOrder(state.orders, Object.assign({}, action.payload, { state: 'accepted' })),
    }

  case ORDER_REFUSED:

    return {
      ...state,
      orders: replaceOrder(state.orders, Object.assign({}, action.payload, { state: 'refused' })),
    }

  case ORDER_CANCELLED:

    return {
      ...state,
      orders: replaceOrder(state.orders, Object.assign({}, action.payload, { state: 'cancelled' })),
    }

  case ORDER_FULFILLED:

    return {
      ...state,
      orders: replaceOrder(state.orders, Object.assign({}, action.payload, { state: 'fulfilled' })),
    }

  case SET_CURRENT_ORDER:

    return {
      ...state,
      order: action.payload,
    }

  case SEARCH_RESULTS:

    return {
      ...state,
      searchResults: action.payload.results,
      searchQuery: action.payload.q,
    }
  }

  return state
}
