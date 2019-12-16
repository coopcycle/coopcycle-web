import { createAction } from 'redux-actions'
import _ from 'lodash'

import i18n from '../../i18n'
import { placeToAddress } from '../../utils/GoogleMaps'

export const FETCH_REQUEST = 'FETCH_REQUEST'
export const FETCH_SUCCESS = 'FETCH_SUCCESS'
export const FETCH_FAILURE = 'FETCH_FAILURE'

export const SET_LAST_ADD_ITEM_REQUEST = 'SET_LAST_ADD_ITEM_REQUEST'
export const CLEAR_LAST_ADD_ITEM_REQUEST = 'CLEAR_LAST_ADD_ITEM_REQUEST'

export const SET_STREET_ADDRESS = 'SET_STREET_ADDRESS'
export const TOGGLE_MOBILE_CART = 'TOGGLE_MOBILE_CART'
export const REPLACE_ERRORS = 'REPLACE_ERRORS'
export const SET_DATE_MODAL_OPEN = 'SET_DATE_MODAL_OPEN'

export const fetchRequest = createAction(FETCH_REQUEST)
export const fetchSuccess = createAction(FETCH_SUCCESS)
export const fetchFailure = createAction(FETCH_FAILURE)

export const setStreetAddress = createAction(SET_STREET_ADDRESS)
export const toggleMobileCart = createAction(TOGGLE_MOBILE_CART)
export const replaceErrors = createAction(REPLACE_ERRORS, (propertyPath, errors) => ({ propertyPath, errors }))

export const setLastAddItemRequest = createAction(SET_LAST_ADD_ITEM_REQUEST, (url, data) => ({ url, data }))
export const clearLastAddItemRequest = createAction(CLEAR_LAST_ADD_ITEM_REQUEST)
export const setDateModalOpen = createAction(SET_DATE_MODAL_OPEN)

function postForm() {

  const $form = $('form[name="cart"]')
  const data = $form.serializeArray()

  return $.post($form.attr('action'), data)
}

function postFormWithTime() {

  const $form = $('form[name="cart"]')
  const defaultData = $form.serializeArray()

  const $timeForm = $('form[name="cart_time"]')
  const timeData = $timeForm.serializeArray()

  const data = defaultData.concat(timeData)

  return $.post($form.attr('action'), data)
}

function notifyListeners(cart) {
  const event = new CustomEvent('cart:change', { detail: cart })
  const listeners = Array.from(document.querySelectorAll('[data-cart-listener]'))
  listeners.forEach(listener => listener.dispatchEvent(event))
}

function handleAjaxResponse(res, dispatch, success) {
  if (success) {
    dispatch(fetchSuccess(res))
    dispatch(clearLastAddItemRequest())
  } else {
    dispatch(fetchFailure(res))
  }

  $('#menu').LoadingOverlay('hide')

  notifyListeners(res.cart)
}

const QUEUE_CART_ITEMS = 'QUEUE_CART_ITEMS'

export function addItem(itemURL, quantity = 1) {

  return dispatch => {

    dispatch(fetchRequest())
    dispatch(setLastAddItemRequest(itemURL, { quantity }))

    return $.post(itemURL, { quantity })
      .then(res => {
        window._paq.push(['trackEvent', 'Checkout', 'addItem'])
        handleAjaxResponse(res, dispatch, true)
      })
      .fail(e => handleAjaxResponse(e.responseJSON, dispatch, false))
  }
}

export function queueAddItem(itemURL, quantity = 1) {

  return {
    queue: QUEUE_CART_ITEMS,
    callback: (next, dispatch) => {

      dispatch(fetchRequest())
      dispatch(setLastAddItemRequest(itemURL, { quantity }))

      $.post(itemURL, { quantity })
        .then(res => {
          window._paq.push(['trackEvent', 'Checkout', 'addItem'])
          handleAjaxResponse(res, dispatch, true)
          next()
        })
        .fail(e => {
          handleAjaxResponse(e.responseJSON, dispatch, false)
          next()
        })
    }
  }
}

export function addItemWithOptions(itemURL, data, quantity = 1) {

  return dispatch => {

    dispatch(fetchRequest())
    dispatch(setLastAddItemRequest(itemURL, data))

    if (Array.isArray(data)) {
      data.push({ name: 'quantity', value: quantity })
    } else {
      data = { ...data, quantity }
    }

    return $.post(itemURL, data)
      .then(res => {
        window._paq.push(['trackEvent', 'Checkout', 'addItemWithOptions'])
        handleAjaxResponse(res, dispatch, true)
      })
      .fail(e => handleAjaxResponse(e.responseJSON, dispatch, false))
  }
}

export function updateItemQuantity(itemID, quantity) {

  return (dispatch, getState) => {

    const { restaurant } = getState()

    const url =
      window.Routing.generate('restaurant_modify_cart_item_quantity', { id: restaurant.id, itemId: itemID })

    dispatch(fetchRequest())

    $.post(url, { quantity })
      .then(res => handleAjaxResponse(res, dispatch, true))
      .fail(e => handleAjaxResponse(e.responseJSON, dispatch, false))
  }
}

export function removeItem(itemID) {

  return (dispatch, getState) => {

    window._paq.push(['trackEvent', 'Checkout', 'removeItem'])

    dispatch(fetchRequest())

    const restaurant = getState().restaurant
    const url = window.Routing.generate('restaurant_remove_from_cart', { id: restaurant.id, cartItemId: itemID })

    const fetchParams = {
      url,
      type: 'DELETE',
    }

    return $.ajax(fetchParams)
      .then(res => handleAjaxResponse(res, dispatch, true))
      .fail(e => handleAjaxResponse(e.responseJSON, dispatch, false))
  }
}

export function sync() {

  return (dispatch) => {

    dispatch(fetchRequest())

    postForm()
      .then(res => handleAjaxResponse(res, dispatch, true))
      .fail(e => handleAjaxResponse(e.responseJSON, dispatch, false))
  }
}

export function geocodeAndSync() {

  const geocoder = new window.google.maps.Geocoder()
  const geocoderOK = window.google.maps.GeocoderStatus.OK

  return (dispatch, getState) => {

    const streetAddress = getState().cart.shippingAddress.streetAddress

    dispatch(fetchRequest())

    geocoder.geocode({ address: streetAddress }, (results, status) => {

      $('#menu').LoadingOverlay('hide')

      if (status === geocoderOK && results.length > 0) {
        const place = results[0]
        const address = placeToAddress(place, streetAddress)
        dispatch(changeAddress(address))
      } else {
        // TODO Set loading to FALSE
      }
    })
  }
}

export function changeDate() {

  return (dispatch) => {

    window._paq.push(['trackEvent', 'Checkout', 'changeDate'])

    dispatch(fetchRequest())

    postFormWithTime()
      .then(res => handleAjaxResponse(res, dispatch, true))
      .fail(e => handleAjaxResponse(e.responseJSON, dispatch, false))
  }
}

export function changeAddress(address) {

  return (dispatch, getState) => {

    window._paq.push(['trackEvent', 'Checkout', 'changeAddress', address.streetAddress])

    const {
      addressFormElements,
      isNewAddressFormElement,
      restaurant
    } = getState()

    if (address.isPrecise) {

      // Change field value immediately
      dispatch(setStreetAddress(address.streetAddress))

      dispatch(fetchRequest())

      if (address.hasOwnProperty('id')) {

        isNewAddressFormElement.value = '0'

        const url =
          window.Routing.generate('restaurant_cart_address', { id: restaurant.id })

        $.post(url, { address: address.id })
          .then(res => handleAjaxResponse(res, dispatch, true))
          .fail(e => handleAjaxResponse(e.responseJSON, dispatch, false))

      } else {

        isNewAddressFormElement.value = '1'

        _.forEach(addressFormElements, (el, key) => {
          if (address.hasOwnProperty(key)) {
            el.value = address[key]
          }
        })

        postForm()
          .then(res => handleAjaxResponse(res, dispatch, true))
          .fail(e => handleAjaxResponse(e.responseJSON, dispatch, false))
      }

    } else {
      dispatch(replaceErrors('shippingAddress', [
        { message: i18n.t('CART_ADDRESS_NOT_ENOUGH_PRECISION') }
      ]))
    }

  }
}

export function goBackToRestaurant() {

  return (dispatch, getState) => {

    const restaurant = getState().cart.restaurant

    window.location.href = window.Routing.generate('restaurant', {
      id: restaurant.id
    })
  }
}

export function clearDate() {

  return (dispatch, getState) => {

    const { restaurant } = getState()

    const url =
      window.Routing.generate('restaurant_cart_clear_time', { id: restaurant.id })

    dispatch(fetchRequest())

    $.post(url)
      .then(res => handleAjaxResponse(res, dispatch, true))
      .fail(e => handleAjaxResponse(e.responseJSON, dispatch, false))

  }
}

export function retryLastAddItemRequest() {

  return (dispatch, getState) => {

    const lastAddItemRequest = getState().lastAddItemRequest

    // TODO Check the request is not null

    dispatch(fetchRequest())

    let data = lastAddItemRequest.data

    if (Array.isArray(data)) {
      data.push({ name: '_clear', value: 'yes' })
    } else {
      data = { ...data, _clear: 'yes' }
    }

    $.post(lastAddItemRequest.url, data)
      .then(res => handleAjaxResponse(res, dispatch, true))
      .fail(e => handleAjaxResponse(e.responseJSON, dispatch, false))
  }
}
