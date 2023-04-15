import { createAction } from 'redux-actions'
import _ from 'lodash'
import axios from 'axios'
import qs from 'qs'

import i18n, { getCountry } from '../../i18n'
import { geocode } from '../../components/AddressAutosuggest'
import Centrifuge from "centrifuge";

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

export const OPEN_INVITE_PEOPLE_TO_ORDER_MODAL = 'OPEN_INVITE_PEOPLE_TO_ORDER_MODAL'
export const CLOSE_INVITE_PEOPLE_TO_ORDER_MODAL = 'CLOSE_INVITE_PEOPLE_TO_ORDER_MODAL'

export const INVITE_PEOPLE_REQUEST = 'INVITE_PEOPLE_REQUEST'
export const INVITE_PEOPLE_REQUEST_SUCCESS = 'INVITE_PEOPLE_REQUEST_SUCCESS'
export const INVITE_PEOPLE_REQUEST_FAILURE = 'INVITE_PEOPLE_REQUEST_FAILURE'

export const OPEN_SET_PLAYER_EMAIL_MODAL = 'OPEN_SET_PLAYER_EMAIL_MODAL'
export const CLOSE_SET_PLAYER_EMAIL_MODAL = 'CLOSE_SET_PLAYER_EMAIL_MODAL'
export const SET_PLAYER_TOKEN = 'SET_PLAYER_TOKEN'

export const PLAYER_UPDATE_EVENT = 'PLAYER_UPDATE_EVENT'

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

export const openInvitePeopleToOrderModal = createAction(OPEN_INVITE_PEOPLE_TO_ORDER_MODAL)
export const closeInvitePeopleToOrderModal = createAction(CLOSE_INVITE_PEOPLE_TO_ORDER_MODAL)

export const invitePeopleRequest = createAction(INVITE_PEOPLE_REQUEST)
export const invitePeopleSuccess = createAction(INVITE_PEOPLE_REQUEST_SUCCESS)
export const invitePeopleFailure = createAction(INVITE_PEOPLE_REQUEST_FAILURE)

export const openSetPlayerEmailModal = createAction(OPEN_SET_PLAYER_EMAIL_MODAL)
export const closeSetPlayerEmailModal = createAction(CLOSE_SET_PLAYER_EMAIL_MODAL)
export const setPlayerToken = createAction(SET_PLAYER_TOKEN)

export const playerUpdateEvent = createAction(PLAYER_UPDATE_EVENT)

const httpClient = axios.create()
function getRoutingParams(params) {

  const urlParams = qs.parse(window.location.search.substring(1))

  if (Object.prototype.hasOwnProperty.call(urlParams, 'embed')
    && (urlParams.embed === '' || urlParams.embed === '1')) {
    params.embed = '1'
  }

  return params
}

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
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest'
    },
    url: $form.data('cartUrl'),
    data: serializeForm().toString(),
  }).then(res => res.data)
}

function postFormWithTime() {

  const $form = $('form[name="cart"]')

  return httpClient.request({
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest'
    },
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

function playerHeader({player: {token}}, headers = {}) {
  if (token !== null) {
    headers = {
      ...headers,
      'X-Player-Token': token
    }
  }
  return headers
}

const callbacks = []

function addCallback(order, cb) {
  callbacks.push({
    order, cb,
  })
}

function applyCallback(order) {
  const callback = _.find(callbacks, (cb) => cb.order === order)
  if (callback) {
    callback.cb()
    callbacks.splice(callbacks.indexOf(callback), 1)
  }
}

const QUEUE_CART_ITEMS = 'QUEUE_CART_ITEMS'

export function addItem(itemURL, quantity = 1) {

  return (dispatch, getState) => {

    dispatch(fetchRequest())
    dispatch(setLastAddItemRequest(itemURL, { quantity }))

    return httpClient.request({
      method: "post",
      url: itemURL,
      data: {quantity},
      headers: playerHeader(getState()),
    }).then(res => {
      window._paq.push(['trackEvent', 'Checkout', 'addItem'])
      handleAjaxResponse(res.data, dispatch)
    }).catch(e => {
      handleAjaxError(e, dispatch)
    })
  }
}

export function queueAddItem(itemURL, quantity = 1) {

  return {
    queue: QUEUE_CART_ITEMS,
    callback: (next, dispatch, getState) => {

      dispatch(fetchRequest())
      dispatch(setLastAddItemRequest(itemURL, { quantity }))


      httpClient.request({
        method: "post",
        url: itemURL,
        data: {quantity},
        headers: playerHeader(getState()),
      }).then(res => {
        window._paq.push(['trackEvent', 'Checkout', 'addItem'])
        handleAjaxResponse(res.data, dispatch)
        next()
      }).catch(e => {
        handleAjaxError(e, dispatch)
        next()
      })
    }
  }
}

export function addItemWithOptions(itemURL, data, quantity = 1) {

  return (dispatch, getState) => {

    dispatch(fetchRequest())
    dispatch(setLastAddItemRequest(itemURL, data))

    if (Array.isArray(data)) {
      data.push({ name: 'quantity', value: quantity })
    } else {
      data = { ...data, quantity }
    }

    data = _.reduce(data, (acc, k) => {
      acc[k.name] = k.value
      return acc
    }, {})

    return httpClient.request({
      method: "post",
      url: itemURL,
      data: qs.stringify(data),
      headers: playerHeader(getState()),
    }).then(res => {
      window._paq.push(['trackEvent', 'Checkout', 'addItemWithOptions'])
      handleAjaxResponse(res.data, dispatch)
    }).catch(e => {
      handleAjaxError(e, dispatch)
    })
  }
}

export function updateItemQuantity(itemID, quantity) {

  return (dispatch, getState) => {

    const { restaurant } = getState()

    const url =
      window.Routing.generate('restaurant_modify_cart_item_quantity', getRoutingParams({ id: restaurant.id, itemId: itemID }))

    dispatch(fetchRequest())

    httpClient.request({
      method: "post",
      url,
      data: {quantity},
      headers: playerHeader(getState()),
    }).then(res => {
      handleAjaxResponse(res.data, dispatch)
    }).catch(e => {
      handleAjaxError(e, dispatch)
    })
  }
}

export function removeItem(itemID) {

  return (dispatch, getState) => {

    window._paq.push(['trackEvent', 'Checkout', 'removeItem'])

    dispatch(fetchRequest())

    const restaurant = getState().restaurant
    const url = window.Routing.generate('restaurant_remove_from_cart', getRoutingParams({ id: restaurant.id, cartItemId: itemID }))

    return httpClient.request({
      method: "delete",
      url,
      headers: playerHeader(getState()),
    }).then(res => {
      handleAjaxResponse(res.data, dispatch)
    }).catch(e => {
      handleAjaxError(e, dispatch)
    })
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

    const { cart, isPlayer } = getState()

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

    if (isPlayer) {
      dispatch(openSetPlayerEmailModal())
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
          window.Routing.generate('restaurant_cart_address', getRoutingParams({ id: restaurant.id }))

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
      window.Routing.generate('restaurant_cart_clear_time', getRoutingParams({ id: restaurant.id }))

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

export function invitePeopleToOrder(guests) {
  return (dispatch, getState) => {
    dispatch(invitePeopleRequest())

    // FIXME: this call is failing with a 401 error response
    return $.post(`${getState().cart['@id']}/invite`, { guests })
      .then(dispatch(invitePeopleSuccess()))
      .fail(dispatch(invitePeopleFailure()))
  }
}

export function setPlayer({email, name, jwt}) {
  return async (dispatch, getState) => {

    const { cart: {id, invitation: slug} } = getState()
    const url = window.Routing.generate('api_orders_add_player_item', getRoutingParams({id}))

    // Try to get jwt if no data is provided
    if (!email && !name && !jwt) {
      let {jwt: token} = await $.getJSON(window.Routing.generate('profile_jwt'))
      jwt = token
    }

    // Try to get data with the /api/me endpoint
    if (!email && !name && jwt) {
      let {data} = await httpClient.get('/api/me', {
        headers: {
          'Accept': 'application/ld+json',
          'Content-Type': 'application/ld+json',
          'Authorization': `Bearer ${jwt}`
        }
      })
      email = data.email
      name = data.username
    }


    // Set player
    if (email && slug && name) {
      httpClient.request({
        method: "post",
        url,
        data: {email, slug, name},
        headers: {
          'Accept': 'application/ld+json',
          'Content-Type': 'application/ld+json'
        }
      }).then(res => {
        dispatch(setPlayerToken(res.data))
        dispatch(closeSetPlayerEmailModal())
      })
    }
  }
}

export function createInvitation() {

  return (dispatch, getState) => {
    dispatch(invitePeopleRequest())

    const { cart: {id} } = getState()
    const url = window.Routing.generate('api_orders_create_invitation_item', getRoutingParams({id}))
    //TODO: Check if this work as guest
    $.getJSON(window.Routing.generate('profile_jwt')).then(({jwt}) => {
      httpClient.request({
        method: 'post',
        url,
        data: {},
        headers: {
          'Authorization': `Bearer ${jwt}`,
          'Accept': 'application/ld+json',
          'Content-Type': 'application/ld+json'
        }
      })
        .then(res => {
          dispatch(invitePeopleSuccess(res.data.invitation))
          dispatch(setPlayer({jwt}))
        })
        .catch(e => dispatch(invitePeopleFailure(e)))
    })

  }
}

export function subscribe() {
  return (dispatch, getState) => {
    const { player, cart: { id } } = getState()
    const protocol = window.location.protocol === 'https:' ? 'wss': 'ws'
    const centrifuge = new Centrifuge(`${protocol}://${window.location.hostname}/centrifugo/connection/websocket`, {
      // In this case, we don't refresh the connection
      // https://github.com/centrifugal/centrifuge-js#refreshendpoint
      refreshAttempts: 0,
      onRefresh: function(ctx, cb) {
        cb({ status: 403 })
      }
    })
    addCallback(id, () => centrifuge.disconnect())

    centrifuge.setToken(player.centrifugo.token)

    centrifuge.subscribe(player.centrifugo.channel, message => {
      dispatch(playerUpdateEvent(message.data.event.data.order))
    })
    centrifuge.connect()
    }
}
export function unsubscribe() {
  return function (dispatch, getState) {
    applyCallback(getState().cart.id)
  }
}
