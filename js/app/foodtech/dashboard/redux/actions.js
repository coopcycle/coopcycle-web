import { createAction } from 'redux-actions'
import Fuse from 'fuse.js'

export const INIT_HTTP_CLIENT = 'INIT_HTTP_CLIENT'
export const REFRESH_TOKEN_SUCCESS = 'REFRESH_TOKEN_SUCCESS'

export const SET_CURRENT_ORDER = 'SET_CURRENT_ORDER'
export const ORDER_CREATED = 'ORDER_CREATED'
export const ORDER_ACCEPTED = 'ORDER_ACCEPTED'
export const ORDER_REFUSED = 'ORDER_REFUSED'
export const ORDER_CANCELLED = 'ORDER_CANCELLED'
export const ORDER_FULFILLED = 'ORDER_FULFILLED'
export const ORDER_DELAYED = 'ORDER_DELAYED'

export const FETCH_REQUEST = 'FETCH_REQUEST'
export const ACCEPT_ORDER_REQUEST_SUCCESS = 'ACCEPT_ORDER_REQUEST_SUCCESS'
export const ACCEPT_ORDER_REQUEST_FAILURE = 'ACCEPT_ORDER_REQUEST_FAILURE'
export const REFUSE_ORDER_REQUEST_SUCCESS = 'REFUSE_ORDER_REQUEST_SUCCESS'
export const REFUSE_ORDER_REQUEST_FAILURE = 'REFUSE_ORDER_REQUEST_FAILURE'
export const DELAY_ORDER_REQUEST_SUCCESS = 'DELAY_ORDER_REQUEST_SUCCESS'
export const DELAY_ORDER_REQUEST_FAILURE = 'DELAY_ORDER_REQUEST_FAILURE'
export const CANCEL_ORDER_REQUEST_SUCCESS = 'CANCEL_ORDER_REQUEST_SUCCESS'
export const CANCEL_ORDER_REQUEST_FAILURE = 'CANCEL_ORDER_REQUEST_FAILURE'
export const FULFILL_ORDER_REQUEST_SUCCESS = 'FULFILL_ORDER_REQUEST_SUCCESS'
export const FULFILL_ORDER_REQUEST_FAILURE = 'FULFILL_ORDER_REQUEST_FAILURE'
export const RESTORE_ORDER_REQUEST_SUCCESS = 'RESTORE_ORDER_REQUEST_SUCCESS'
export const RESTORE_ORDER_REQUEST_FAILURE = 'RESTORE_ORDER_REQUEST_FAILURE'

export const CHANGE_RESTAURANT_STATE = 'CHANGE_RESTAURANT_STATE'

export const SEARCH_RESULTS = 'SEARCH_RESULTS'

export const SET_REUSABLE_PACKAGINGS = 'SET_REUSABLE_PACKAGINGS'
export const OPEN_LOOPEAT_SECTION = 'OPEN_LOOPEAT_SECTION'
export const CLOSE_LOOPEAT_SECTION = 'CLOSE_LOOPEAT_SECTION'
export const SET_LOOPEAT_FORMATS = 'SET_LOOPEAT_FORMATS'
export const UPDATE_LOOPEAT_FORMATS_SUCCESS = 'UPDATE_LOOPEAT_FORMATS_SUCCESS'

export const ACTIVE_TAB = 'ACTIVE_TAB'

export const orderCreated = createAction(ORDER_CREATED)
export const orderAccepted = createAction(ORDER_ACCEPTED)
export const orderRefused = createAction(ORDER_REFUSED)
export const orderCancelled = createAction(ORDER_CANCELLED)
export const orderFulfilled = createAction(ORDER_FULFILLED)
export const orderDelayed = createAction(ORDER_DELAYED)

export const fetchRequest = createAction(FETCH_REQUEST)
export const acceptOrderRequestSuccess = createAction(ACCEPT_ORDER_REQUEST_SUCCESS)
export const acceptOrderRequestFailure = createAction(ACCEPT_ORDER_REQUEST_FAILURE)
export const refuseOrderRequestSuccess = createAction(REFUSE_ORDER_REQUEST_SUCCESS)
export const refuseOrderRequestFailure = createAction(REFUSE_ORDER_REQUEST_FAILURE)
export const delayOrderRequestSuccess = createAction(DELAY_ORDER_REQUEST_SUCCESS)
export const delayOrderRequestFailure = createAction(DELAY_ORDER_REQUEST_FAILURE)
export const cancelOrderRequestSuccess = createAction(CANCEL_ORDER_REQUEST_SUCCESS)
export const cancelOrderRequestFailure = createAction(CANCEL_ORDER_REQUEST_FAILURE)
export const fulfillOrderRequestSuccess = createAction(FULFILL_ORDER_REQUEST_SUCCESS)
export const fulfillOrderRequestFailure = createAction(FULFILL_ORDER_REQUEST_FAILURE)
export const restoreOrderRequestSuccess = createAction(RESTORE_ORDER_REQUEST_SUCCESS)
export const restoreOrderRequestFailure = createAction(RESTORE_ORDER_REQUEST_FAILURE)

export const searchResults = createAction(SEARCH_RESULTS, (q, results) => ({ q, results }))

export const setReusablePackagings = createAction(SET_REUSABLE_PACKAGINGS, (restaurant, reusablePackagings) => ({ restaurant, reusablePackagings }))
export const openLoopeatSection = createAction(OPEN_LOOPEAT_SECTION)
export const closeLoopeatSection = createAction(CLOSE_LOOPEAT_SECTION)
export const setLoopeatFormats = createAction(SET_LOOPEAT_FORMATS)
export const updateLoopeatFormatsSuccess = createAction(UPDATE_LOOPEAT_FORMATS_SUCCESS)

export const setActiveTab = createAction(ACTIVE_TAB)

export const initHttpClient = createAction(INIT_HTTP_CLIENT)
export const refreshTokenSuccess = createAction(REFRESH_TOKEN_SUCCESS)

const _setCurrentOrder = createAction(SET_CURRENT_ORDER)

export function setCurrentOrder(order) {

  return (dispatch, getState) => {

    const { currentRoute, date, restaurant, httpClient } = getState()

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
        order: order['@id']
      }
    }

    if (order) {
      httpClient.get(order['@id'])
        .then(res => {
          dispatch(_setCurrentOrder(res.data))
          window.history.replaceState(
            {},
            document.title,
            window.Routing.generate(currentRoute, routeParams)
          )
        })
    } else {
      dispatch(_setCurrentOrder(order))
      window.history.replaceState(
        {},
        document.title,
        window.Routing.generate(currentRoute, routeParams)
      )
    }

  }
}

export function acceptOrder(order) {

  return (dispatch, getState) => {
    dispatch(fetchRequest())

    const { httpClient } = getState()

    httpClient.put(order['@id'] + '/accept')
      .then(res => dispatch(acceptOrderRequestSuccess(res.data)))
      .catch(e => dispatch(acceptOrderRequestFailure(e)))
  }
}

export function refuseOrder(order, reason) {

  return (dispatch, getState) => {
    dispatch(fetchRequest())

    const { httpClient } = getState()

    httpClient.put(order['@id'] + '/refuse', { reason })
      .then(res => dispatch(refuseOrderRequestSuccess(res.data)))
      .catch(e => dispatch(refuseOrderRequestFailure(e)))
  }
}

export function delayOrder(order) {

  return (dispatch, getState) => {
    dispatch(fetchRequest())

    const { httpClient } = getState()

    httpClient.put(order['@id'] + '/delay')
      .then(res => dispatch(delayOrderRequestSuccess(res.data)))
      .catch(e => dispatch(delayOrderRequestFailure(e)))
  }
}

export function cancelOrder(order, reason) {

  return (dispatch, getState) => {
    dispatch(fetchRequest())

    const { httpClient } = getState()

    httpClient.put(order['@id'] + '/cancel', { reason })
      .then(res => dispatch(cancelOrderRequestSuccess(res.data)))
      .catch(e => dispatch(cancelOrderRequestFailure(e)))
  }
}

export function fulfillOrder(order) {

  return (dispatch, getState) => {
    dispatch(fetchRequest())

    const { httpClient } = getState()

    httpClient.put(order['@id'] + '/fulfill')
      .then(res => dispatch(fulfillOrderRequestSuccess(res.data)))
      .catch(e => dispatch(fulfillOrderRequestFailure(e)))
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
    const { httpClient } = getState()
    httpClient.put(restaurant['@id'], { state })
  }
}

const fuseOptions = {
  shouldSort: true,
  includeScore: true,
  keys: [
    {
      name: 'number',
      weight: 0.4
    },
    {
      name: 'vendor.name',
      weight: 0.1
    },
    {
      name: 'shippingAddress.streetAddress',
      weight: 0.1
    },
    {
      name: 'customer.username',
      weight: 0.1
    },
    {
      name: 'customer.email',
      weight: 0.1
    },
    {
      name: 'customer.givenName',
      weight: 0.1
    },
    {
      name: 'customer.familyName',
      weight: 0.1
    },
  ]
}

export function search(q) {

  return (dispatch, getState) => {
    const { orders } = getState()
    const fuse = new Fuse(orders, fuseOptions)
    const results = fuse.search(q)
    dispatch(searchResults(q, results.map(result => result.item)))
  }
}

export function toggleReusablePackagings(order) {

  return (dispatch, getState) => {

    const { httpClient, isLoopeatSectionOpen } = getState()

    if (isLoopeatSectionOpen) {
      dispatch(closeLoopeatSection())
      return
    }

    httpClient.get(order['@id'] + '/loopeat_formats')
      .then(res => {
        dispatch(setLoopeatFormats(res.data.items))
        dispatch(openLoopeatSection())
      })
  }
}

export function updateLoopeatFormats(order, loopeatFormats) {

  return (dispatch, getState) => {

    const { httpClient } = getState()

    httpClient.put(order['@id'] + '/loopeat_formats', {
      items: loopeatFormats,
    })
      .then(res => {
        dispatch(updateLoopeatFormatsSuccess(res.data))
        dispatch(closeLoopeatSection())
      })
  }
}

export function restoreOrder(order) {

  return (dispatch, getState) => {
    dispatch(fetchRequest())

    const { httpClient } = getState()

    httpClient.put(order['@id'] + '/restore')
      .then(res => dispatch(restoreOrderRequestSuccess(res.data)))
      .catch(e => dispatch(restoreOrderRequestFailure(e)))
  }
}
