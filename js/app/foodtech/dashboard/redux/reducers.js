import { combineReducers } from 'redux'
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
} from './actions'

const initialState = {
  orders: [],
  order: null,
  date: moment().format('YYYY-MM-DD'),
  jwt: ''
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

const orders = (state = initialState.orders, action) => {
  let newState

  // TODO
  // Make sure orders are for the current date
  // We need to use a unique reducer to achieve this

  switch (action.type) {
  case ACCEPT_ORDER_REQUEST_SUCCESS:
  case REFUSE_ORDER_REQUEST_SUCCESS:
  case CANCEL_ORDER_REQUEST_SUCCESS:

    return replaceOrder(state, action.payload)

  case ORDER_CREATED:

    newState = state.slice()
    newState.push(action.payload)

    return newState

  case ORDER_ACCEPTED:

    return replaceOrder(state, Object.assign({}, action.payload, { state: 'accepted' }))

  case ORDER_REFUSED:

    return replaceOrder(state, Object.assign({}, action.payload, { state: 'refused' }))

  case ORDER_CANCELLED:

    return replaceOrder(state, Object.assign({}, action.payload, { state: 'cancelled' }))

  case ORDER_FULFILLED:

    return replaceOrder(state, Object.assign({}, action.payload, { state: 'fulfilled' }))

  default:

    return state
  }
}

const order = (state = initialState.order, action) => {
  switch (action.type) {
  case ACCEPT_ORDER_REQUEST_SUCCESS:
  case REFUSE_ORDER_REQUEST_SUCCESS:
  case CANCEL_ORDER_REQUEST_SUCCESS:

    return null

  case SET_CURRENT_ORDER:

    return action.payload

  default:

    return state
  }
}

const date = (state = initialState.date, action) => {
  switch (action.type) {
  default:

    return state
  }
}

const jwt = (state = initialState.jwt, action) => {
  switch (action.type) {
  default:

    return state
  }
}

export default combineReducers({
  orders,
  order,
  date,
  jwt,
})
