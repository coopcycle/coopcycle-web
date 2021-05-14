import { createAction } from 'redux-actions'
import _ from 'lodash'
import axios from 'axios'

import i18n, { getCountry } from '../../i18n'
import { geocode } from '../../components/AddressAutosuggest'

export const FETCH_REQUEST = 'FETCH_REQUEST'
export const FETCH_SUCCESS = 'FETCH_SUCCESS'
export const FETCH_FAILURE = 'FETCH_FAILURE'

export const SET_LAST_ADD_ITEM_REQUEST = 'SET_LAST_ADD_ITEM_REQUEST'
export const CLEAR_LAST_ADD_ITEM_REQUEST = 'CLEAR_LAST_ADD_ITEM_REQUEST'

export const SET_STREET_ADDRESS = 'SET_STREET_ADDRESS'
export const TOGGLE_MOBILE_CART = 'TOGGLE_MOBILE_CART'
export const REPLACE_ERRORS = 'REPLACE_ERRORS'
export const SET_DATE_MODAL_OPEN = 'SET_DATE_MODAL_OPEN'
export const CLOSE_ADDRESS_MODAL = 'CLOSE_ADDRESS_MODAL'
export const GEOCODING_FAILURE = 'GEOCODING_FAILURE'
export const OPEN_ADDRESS_MODAL = 'OPEN_ADDRESS_MODAL'

export const ENABLE_TAKEAWAY = 'ENABLE_TAKEAWAY'
export const DISABLE_TAKEAWAY = 'DISABLE_TAKEAWAY'

export const OPEN_PRODUCT_OPTIONS_MODAL = 'OPEN_PRODUCT_OPTIONS_MODAL'
export const CLOSE_PRODUCT_OPTIONS_MODAL = 'CLOSE_PRODUCT_OPTIONS_MODAL'
export const OPEN_PRODUCT_DETAILS_MODAL = 'OPEN_PRODUCT_DETAILS_MODAL'
export const CLOSE_PRODUCT_DETAILS_MODAL = 'CLOSE_PRODUCT_DETAILS_MODAL'

export const fetchRequest = createAction(FETCH_REQUEST)
export const fetchSuccess = createAction(FETCH_SUCCESS)
export const fetchFailure = createAction(FETCH_FAILURE)

export const setStreetAddress = createAction(SET_STREET_ADDRESS)
export const toggleMobileCart = createAction(TOGGLE_MOBILE_CART)
export const replaceErrors = createAction(REPLACE_ERRORS, (propertyPath, errors) => ({ propertyPath, errors }))

export const setLastAddItemRequest = createAction(SET_LAST_ADD_ITEM_REQUEST, (url, data) => ({ url, data }))
export const clearLastAddItemRequest = createAction(CLEAR_LAST_ADD_ITEM_REQUEST)
export const setDateModalOpen = createAction(SET_DATE_MODAL_OPEN)
export const closeAddressModal = createAction(CLOSE_ADDRESS_MODAL)
export const openAddressModal = createAction(OPEN_ADDRESS_MODAL)

export const geocodingFailure = createAction(GEOCODING_FAILURE)

export const closeProductOptionsModal = createAction(CLOSE_PRODUCT_OPTIONS_MODAL)
export const openProductOptionsModal =
  createAction(OPEN_PRODUCT_OPTIONS_MODAL, (name, options, images, price, code, formAction) => ({ name, options, images, price, code, formAction }))

export const closeProductDetailsModal = createAction(CLOSE_PRODUCT_DETAILS_MODAL)
export const openProductDetailsModal =
  createAction(OPEN_PRODUCT_DETAILS_MODAL, (name, images, price, formAction) => ({ name, images, price, formAction }))

const httpClient = axios.create()

httpClient.defaults.headers.common['Content-Type']     = 'application/x-www-form-urlencoded'
httpClient.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

function serializeForm(withTime = false) {

  const params = new URLSearchParams()

  $('form[name="cart"]').serializeArray().forEach(field => {
    params.append(field.name, field.value)
  })

  if (withTime) {
    $('form[name="cart_time"]').serializeArray().forEach(field => {
      params.append(field.name, field.value)
    })
  }

  return params
}

function postForm() {

  const $form = $('form[name="cart"]')

  return httpClient.request({
    method: 'POST',
    url: $form.data('cartUrl'),
    data: serializeForm().toString(),
  }).then(res => res.data)
}

function postFormWithTime() {

  const $form = $('form[name="cart"]')

  return httpClient.request({
    method: 'POST',
    url: $form.data('cartUrl'),
    data: serializeForm(true).toString(),
  }).then(res => res.data)
}

function notifyListeners(cart) {
  const event = new CustomEvent('cart:change', { detail: cart })
  const listeners = Array.from(document.querySelectorAll('[data-cart-listener]'))
  listeners.forEach(listener => listener.dispatchEvent(event))
}

function handleAjaxResponse(res, dispatch, broadcast = true) {
  dispatch(fetchSuccess(res))
  $('#menu').LoadingOverlay('hide')
  if (broadcast) {
    notifyListeners(res.cart)
  }
}

function handleAjaxError(e, dispatch) {
  dispatch(fetchFailure())
}

const QUEUE_CART_ITEMS = 'QUEUE_CART_ITEMS'

export function addItem(itemURL, quantity = 1) {

  return dispatch => {

    dispatch(fetchRequest())
    dispatch(setLastAddItemRequest(itemURL, { quantity }))

    return $.post(itemURL, { quantity })
      .then(res => {
        window._paq.push(['trackEvent', 'Checkout', 'addItem'])
        handleAjaxResponse(res, dispatch)
      })
      .fail(e => handleAjaxResponse(e.responseJSON, dispatch))
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
          handleAjaxResponse(res, dispatch)
          next()
        })
        .fail(e => {
          handleAjaxResponse(e.responseJSON, dispatch)
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
        handleAjaxResponse(res, dispatch)
      })
      .fail(e => handleAjaxResponse(e.responseJSON, dispatch))
  }
}

export function updateItemQuantity(itemID, quantity) {

  return (dispatch, getState) => {

    const { restaurant } = getState()

    const url =
      window.Routing.generate('restaurant_modify_cart_item_quantity', { id: restaurant.id, itemId: itemID })

    dispatch(fetchRequest())

    $.post(url, { quantity })
      .then(res => handleAjaxResponse(res, dispatch))
      .fail(e   => handleAjaxError(e, dispatch))
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
      .then(res => handleAjaxResponse(res, dispatch))
      .fail(e   => handleAjaxError(e, dispatch))
  }
}

function geocodeAndSync() {

  return (dispatch, getState) => {

    const { cart } = getState()

    dispatch(fetchRequest())

    if (getCountry() === 'gb' && cart.shippingAddress.geo) {
      dispatch(changeAddress({
        ...cart.shippingAddress,
        isPrecise: true,
      }))

      return
    }

    geocode(cart.shippingAddress.streetAddress).then(address => {

      $('#menu').LoadingOverlay('hide')

      if (address) {
        dispatch(changeAddress({
          ...cart.shippingAddress,
          ...address,
        }))
      } else {
        dispatch(geocodingFailure())
      }
    })
  }
}

export function sync() {

  return (dispatch, getState) => {

    const { cart } = getState()

    if (cart.takeaway) {
      $('#menu').LoadingOverlay('hide')
      return
    }

    if (cart.shippingAddress && !cart.shippingAddress.streetAddress) {
      dispatch(fetchRequest())
      postForm()
        .then(res => handleAjaxResponse(res, dispatch, false))
        .catch(e  => handleAjaxError(e, dispatch))
    } else {
      dispatch(geocodeAndSync())
    }
  }
}

export function changeDate() {

  return (dispatch) => {

    window._paq.push(['trackEvent', 'Checkout', 'changeDate'])

    dispatch(fetchRequest())

    postFormWithTime()
      .then(res => handleAjaxResponse(res, dispatch, false))
      .catch(e  => handleAjaxError(e, dispatch))
  }
}

export function mapAddressFields(address) {

  return (dispatch, getState) => {

    const { addressFormElements } = getState()

    _.forEach(addressFormElements, (el, key) => {
      const value = _.get(address, key)
      if (value) {
        el.value = value
      }
    })
  }
}

export function changeAddress(address) {

  return (dispatch, getState) => {

    window._paq.push(['trackEvent', 'Checkout', 'changeAddress', address.streetAddress])

    const {
      isNewAddressFormElement,
      restaurant
    } = getState()

    if (address.isPrecise) {

      // Change field value immediately
      dispatch(setStreetAddress(address.streetAddress))

      dispatch(fetchRequest())

      if (address['@id']) {

        isNewAddressFormElement.value = '0'

         // This must be done *BEFORE* posting the form
        dispatch(disableTakeaway(false))

        const url =
          window.Routing.generate('restaurant_cart_address', { id: restaurant.id })

        $.post(url, { address: address['@id'] })
          .then(res => handleAjaxResponse(res, dispatch, false))
          .fail(e   => handleAjaxError(e, dispatch))

      } else {

        isNewAddressFormElement.value = '1'

        // This must be done *BEFORE* posting the form
        dispatch(mapAddressFields(address))
        dispatch(disableTakeaway(false))

        postForm()
          .then(res => handleAjaxResponse(res, dispatch, false))
          .catch(e  => handleAjaxError(e, dispatch))
      }

    } else {
      setTimeout(() => dispatch(replaceErrors('shippingAddress', [
        {
          message: i18n.t('CART_ADDRESS_NOT_ENOUGH_PRECISION'),
          code: 'Order::ADDRESS_NOT_PRECISE',
        }
      ])), 250)
    }

  }
}

export function clearDate() {

  return (dispatch, getState) => {

    const { restaurant } = getState()

    const url =
      window.Routing.generate('restaurant_cart_clear_time', { id: restaurant.id })

    dispatch(fetchRequest())

    $.post(url)
      .then(res => handleAjaxResponse(res, dispatch, false))
      .fail(e   => handleAjaxError(e, dispatch))

  }
}

const _enableTakeaway = createAction(ENABLE_TAKEAWAY)
const _disableTakeaway = createAction(DISABLE_TAKEAWAY)

export function enableTakeaway(post = true) {

  return (dispatch) => {

    const $form = $('form[name="cart"]')
    const $takeaway = $form.find('input[name="cart[takeaway]"]')

    if ($takeaway.length === 1) {

      $takeaway.prop('checked', true)

      dispatch(_enableTakeaway())

      if (post) {
        dispatch(fetchRequest())
        postForm()
          .then(res => handleAjaxResponse(res, dispatch))
          .catch(e  => handleAjaxError(e, dispatch))
      }
    }
  }
}

export function disableTakeaway(post = true) {

  return (dispatch) => {

    const $form = $('form[name="cart"]')
    const $takeaway = $form.find('input[name="cart[takeaway]"]')

    if ($takeaway.length === 1) {

      $takeaway.prop('checked', false)

      dispatch(_disableTakeaway())

      if (post) {
        dispatch(fetchRequest())
        postForm()
          .then(res => handleAjaxResponse(res, dispatch))
          .catch(e  => handleAjaxError(e, dispatch))
      }
    }
  }
}

// FIXME
// It is actually bad to name this action after what it really does
// It should be named "startNewSession" or something like that
// i.e something that represents what the user actually does
export function retryLastAddItemRequest() {

  return (dispatch, getState) => {

    const lastAddItemRequest = getState().lastAddItemRequest

    if (!lastAddItemRequest || !lastAddItemRequest.url) {
      return
    }

    dispatch(fetchRequest())

    let data = lastAddItemRequest.data

    if (Array.isArray(data)) {
      data.push({ name: '_clear', value: 'yes' })
    } else {
      data = { ...data, _clear: 'yes' }
    }

    $.post(lastAddItemRequest.url, data)
      .then(res => {
        dispatch(clearLastAddItemRequest())
        handleAjaxResponse(res, dispatch)
      })
      .fail(e => handleAjaxError(e, dispatch))
  }
}
