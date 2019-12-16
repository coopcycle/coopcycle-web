import { createAction } from 'redux-actions'
import axios from 'axios'

export const SET_CURRENT_ORDER = 'SET_CURRENT_ORDER'
export const ORDER_CREATED = 'ORDER_CREATED'
export const ORDER_ACCEPTED = 'ORDER_ACCEPTED'
export const ORDER_REFUSED = 'ORDER_REFUSED'
export const ORDER_CANCELLED = 'ORDER_CANCELLED'
export const ORDER_FULFILLED = 'ORDER_FULFILLED'

export const FETCH_REQUEST = 'FETCH_REQUEST'
export const ACCEPT_ORDER_REQUEST_SUCCESS = 'ACCEPT_ORDER_REQUEST_SUCCESS'
export const ACCEPT_ORDER_REQUEST_FAILURE = 'ACCEPT_ORDER_REQUEST_FAILURE'
export const REFUSE_ORDER_REQUEST_SUCCESS = 'REFUSE_ORDER_REQUEST_SUCCESS'
export const REFUSE_ORDER_REQUEST_FAILURE = 'REFUSE_ORDER_REQUEST_FAILURE'
export const DELAY_ORDER_REQUEST_SUCCESS = 'DELAY_ORDER_REQUEST_SUCCESS'
export const DELAY_ORDER_REQUEST_FAILURE = 'DELAY_ORDER_REQUEST_FAILURE'
export const CANCEL_ORDER_REQUEST_SUCCESS = 'CANCEL_ORDER_REQUEST_SUCCESS'
export const CANCEL_ORDER_REQUEST_FAILURE = 'CANCEL_ORDER_REQUEST_FAILURE'

export const CHANGE_RESTAURANT_STATE = 'CHANGE_RESTAURANT_STATE'

export const orderCreated = createAction(ORDER_CREATED)
export const orderAccepted = createAction(ORDER_ACCEPTED)
export const orderRefused = createAction(ORDER_REFUSED)
export const orderCancelled = createAction(ORDER_CANCELLED)
export const orderFulfilled = createAction(ORDER_FULFILLED)

export const fetchRequest = createAction(FETCH_REQUEST)
export const acceptOrderRequestSuccess = createAction(ACCEPT_ORDER_REQUEST_SUCCESS)
export const acceptOrderRequestFailure = createAction(ACCEPT_ORDER_REQUEST_FAILURE)
export const refuseOrderRequestSuccess = createAction(REFUSE_ORDER_REQUEST_SUCCESS)
export const refuseOrderRequestFailure = createAction(REFUSE_ORDER_REQUEST_FAILURE)
export const delayOrderRequestSuccess = createAction(DELAY_ORDER_REQUEST_SUCCESS)
export const delayOrderRequestFailure = createAction(DELAY_ORDER_REQUEST_FAILURE)
export const cancelOrderRequestSuccess = createAction(CANCEL_ORDER_REQUEST_SUCCESS)
export const cancelOrderRequestFailure = createAction(CANCEL_ORDER_REQUEST_FAILURE)

const _setCurrentOrder = createAction(SET_CURRENT_ORDER)

export function setCurrentOrder(order) {

  return (dispatch, getState) => {

    const { currentRoute, date, restaurant } = getState()

    let routeParams = { date }

    if (restaurant) {
      routeParams = {
        ...routeParams,
        restaurantId: restaurant.id
      }
    }

    if (order) {
      routeParams = {
        ...routeParams,
        order: order.id
      }
    }

    window.history.replaceState(
      {},
      document.title,
      window.Routing.generate(currentRoute, routeParams)
    )

    dispatch(_setCurrentOrder(order))
  }
}

export function acceptOrder(order) {

  return (dispatch, getState) => {
    dispatch(fetchRequest())

    const url = window.Routing.generate(getState().acceptOrderRoute, { id: order.id })

    $.post(url)
      .then(res => dispatch(acceptOrderRequestSuccess(res)))
      .fail(e => dispatch(acceptOrderRequestFailure(e)))
  }
}

export function refuseOrder(order) {

  return (dispatch, getState) => {
    dispatch(fetchRequest())

    const url = window.Routing.generate(getState().refuseOrderRoute, { id: order.id })

    $.post(url)
      .then(res => dispatch(refuseOrderRequestSuccess(res)))
      .fail(e => dispatch(refuseOrderRequestFailure(e)))
  }
}

export function delayOrder(order) {

  return (dispatch, getState) => {
    dispatch(fetchRequest())

    const url = window.Routing.generate(getState().delayOrderRoute, { id: order.id })

    $.post(url)
      .then(res => dispatch(delayOrderRequestSuccess(res)))
      .fail(e => dispatch(delayOrderRequestFailure(e)))
  }
}

export function cancelOrder(order) {

  return (dispatch, getState) => {
    dispatch(fetchRequest())

    const url = window.Routing.generate(getState().cancelOrderRoute, { id: order.id })

    $.post(url)
      .then(res => dispatch(cancelOrderRequestSuccess(res)))
      .fail(e => dispatch(cancelOrderRequestFailure(e)))
  }
}

export function setPreparationDelay(delay) {

  return () => {
    const url = window.Routing.generate('admin_foodtech_settings')
    $.post(url, { 'preparation_delay': delay })
  }
}

export function changeStatus(restaurant, state) {

  return (dispatch, getState) => {
    const { jwt } = getState()
    axios.put(restaurant['@id'], { state }, { headers: {
      'Authorization': `Bearer ${jwt}`,
      'Accept': 'application/ld+json',
      'Content-Type': 'application/ld+json'
    }
    })
  }
}
