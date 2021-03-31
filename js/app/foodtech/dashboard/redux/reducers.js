import _ from 'lodash'
import Moment from 'moment'
import { extendMoment } from 'moment-range'

const moment = extendMoment(Moment)

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
  FULFILL_ORDER_REQUEST_SUCCESS,
  FULFILL_ORDER_REQUEST_FAILURE,
  SEARCH_RESULTS,
  ACTIVE_TAB,
  INIT_HTTP_CLIENT,
  REFRESH_TOKEN_SUCCESS,
} from './actions'

export const initialState = {
  orders: [],
  order: null,
  date: moment().format('YYYY-MM-DD'),
  jwt: '',
  centrifugo: {
    token: '',
    namespace: '',
    username: '',
  },
  restaurant: null,
  isFetching: false,
  currentRoute: 'admin_foodtech_dashboard',
  preparationDelay: 0,
  showSettings: true,
  showSearch: false,
  searchQuery: '',
  searchResults: [],
  activeTab: 'new',
  httpClient: null,
  initialOrder: null,
}

// The "force" parameter is useful for multi vendor orders,
// to add the order even if it's not already there
function replaceOrder(orders, order, force = false) {

  const orderIndex = _.findIndex(orders, o => o['@id'] === order['@id'])

  if (-1 === orderIndex && force) {

    return orders.concat([ order ])
  }

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
  case FULFILL_ORDER_REQUEST_FAILURE:

    return {
      ...state,
      isFetching: false,
    }

  case ACCEPT_ORDER_REQUEST_SUCCESS:
  case REFUSE_ORDER_REQUEST_SUCCESS:
  case CANCEL_ORDER_REQUEST_SUCCESS:
  case DELAY_ORDER_REQUEST_SUCCESS:
  case FULFILL_ORDER_REQUEST_SUCCESS:

    return {
      ...state,
      isFetching: false,
      orders: replaceOrder(state.orders, action.payload),
      order: null,
    }

  case ORDER_CREATED:

    const range = moment.range(
      moment(state.date).set({ hour: 0, minute: 0, second: 0 }),
      moment(state.date).set({ hour: 23, minute: 59, second: 59 })
    )

    const shippingTimeRange = moment.range(action.payload.shippingTimeRange)

    // The order is not for the current date, skip
    if (!shippingTimeRange.overlaps(range)) {

      return state
    }

    const newOrders = state.orders.slice()

    // Make sure to keep only needed data
    newOrders.push(_.pick(action.payload, [
      '@id',
      'customer',
      'vendor',
      'shippingTimeRange',
      'shippingAddress',
      'number',
      'total',
      'state',
      'assignedTo',
      'takeaway',
      'fulfillmentMethod',
    ]))

    return {
      ...state,
      orders: newOrders,
    }

  case ORDER_ACCEPTED:

    return {
      ...state,
      orders: replaceOrder(state.orders, Object.assign({}, action.payload, { state: 'accepted' }), true),
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

  case ACTIVE_TAB:

    return {
      ...state,
      activeTab: action.payload,
    }

  case INIT_HTTP_CLIENT:

    return {
      ...state,
      httpClient: action.payload
    }

  case REFRESH_TOKEN_SUCCESS:

    return {
      ...state,
      jwt: action.payload
    }
  }

  return state
}
