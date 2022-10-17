import { createAction } from 'redux-actions'

import createHttpClient from '../../client'

export const REFRESH_TOKEN_SUCCESS = 'REFRESH_TOKEN_SUCCESS'

export const CREATE_ADHOC_ORDER_REQUEST = 'CREATE_ADHOC_ORDER_REQUEST'
export const CREATE_ADHOC_ORDER_REQUEST_SUCCESS = 'CREATE_ADHOC_ORDER_REQUEST_SUCCESS'
export const CREATE_ADHOC_ORDER_REQUEST_FAILURE = 'CREATE_ADHOC_ORDER_REQUEST_FAILURE'

export const SEARCH_ADHOC_ORDER_REQUEST = 'SEARCH_ADHOC_ORDER_REQUEST'
export const SEARCH_ADHOC_ORDER_REQUEST_SUCCESS = 'SEARCH_ADHOC_ORDER_REQUEST_SUCCESS'
export const SEARCH_ADHOC_ORDER_REQUEST_FAILURE = 'SEARCH_ADHOC_ORDER_REQUEST_FAILURE'

export const CLEAR_ADHOC_ORDER = 'CLEAR_ADHOC_ORDER'

export const refreshTokenSuccess = createAction(REFRESH_TOKEN_SUCCESS)
export const createAdhocOrderRequest = createAction(CREATE_ADHOC_ORDER_REQUEST)
export const createAdhocOrderRequestSuccess = createAction(CREATE_ADHOC_ORDER_REQUEST_SUCCESS)
export const createAdhocOrderRequestFailure = createAction(CREATE_ADHOC_ORDER_REQUEST_FAILURE)

export const searchAdhocOrderRequest = createAction(SEARCH_ADHOC_ORDER_REQUEST)
export const searchAdhocOrderRequestSuccess = createAction(SEARCH_ADHOC_ORDER_REQUEST_SUCCESS)
export const searchAdhocOrderRequestFailure = createAction(SEARCH_ADHOC_ORDER_REQUEST_FAILURE)

export const clearAdhocOrder = createAction(CLEAR_ADHOC_ORDER)

export function createAdhocOrder(adhocOrder) {
  return function (dispatch, getState) {
    const { jwt } = getState()

    dispatch(createAdhocOrderRequest())

    const fetchToken = window.Routing.generate('profile_jwt')

    const httpClient = createHttpClient(
      getState().jwt,
      () => new Promise((resolve) => {
        // TODO Check response is OK, reject promise
        $.getJSON(fetchToken).then(result => resolve(result.jwt))
      }),
      token => dispatch(refreshTokenSuccess(token))
    )

    return httpClient.request({
      method: 'post',
      url: '/api/orders/adhoc',
      data: adhocOrder,
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      }
    })
      .then((res) => {
        dispatch(createAdhocOrderRequestSuccess(res.data))
      })
      .catch((err) => {
        dispatch(createAdhocOrderRequestFailure(err))
        throw err;
      })
  }

}

export function searchAdhocOrder(orderNumber) {
  return function (dispatch, getState) {
    const { jwt, restaurant } = getState()

    dispatch(searchAdhocOrderRequest())

    dispatch(clearAdhocOrder())

    const fetchToken = window.Routing.generate('profile_jwt')

    const httpClient = createHttpClient(
      getState().jwt,
      () => new Promise((resolve) => {
        // TODO Check response is OK, reject promise
        $.getJSON(fetchToken).then(result => resolve(result.jwt))
      }),
      token => dispatch(refreshTokenSuccess(token))
    )

    return httpClient.request({
      method: 'get',
      url: `/api/orders/adhoc/search?hub=${restaurant.hub['@id']}&orderNumber=${orderNumber}&restaurant=${restaurant['@id']}`,
      headers: {
        'Authorization': `Bearer ${jwt}`,
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json'
      }
    })
      .then((res) => {
        dispatch(searchAdhocOrderRequestSuccess(res.data))
      })
      .catch((err) => {
        dispatch(searchAdhocOrderRequestFailure(err))
        throw err
      })
  }
}