import React from 'react'
import { render } from 'react-dom'
import moment from 'moment'
import _ from 'lodash'
import { geocodeByAddress } from 'react-places-autocomplete'

import Cart from './Cart.jsx'
import CartTop from './CartTop.jsx'
import i18n from '../i18n'
import { placeToAddress } from '../utils/GoogleMaps'

let isXsDevice = $('.visible-xs').is(':visible')

window.CoopCycle = window.CoopCycle || {}
window._paq = window._paq || []

class CartHelper {

  constructor(options = {}) {
    this.options = options
  }

  init(el, options) {

    this.options = Object.assign(this.options, options)

    const {
      adjustments,
      date,
      items,
      itemsTotal,
      shippingAddress,
      total
    } = window.AppData.Cart

    const geohash = sessionStorage.getItem('search_geohash') || ''
    const availabilities = options.restaurant.availabilities

    const streetAddress = shippingAddress ? shippingAddress.streetAddress : (sessionStorage.getItem('search_address') || '')

    const closestDeliveryDate = _.first(availabilities)
    let deliveryDate = closestDeliveryDate

    // Verify if stored date is not in the past
    if (date) {
      if (!moment(date).isBefore(moment(closestDeliveryDate))) {
        deliveryDate = date
      }
    }

    this.cartComponentRef = React.createRef()

    const onRender = () => {
      // There is no shipping address saved in cart
      if (!shippingAddress && streetAddress) {
        this._setLoading(true)
        geocodeByAddress(streetAddress)
          .then(results => {
            if (results.length === 1) {
              const place = results[0]
              const address = placeToAddress(place)
              this._onAddressChange(address)
            }
          })
          .finally(() => {
            this._setLoading(false)
          })
      } else {
        this._postCart()
      }
    }

    render(
      <Cart
        ref={ this.cartComponentRef }
        streetAddress={ streetAddress }
        geohash={ geohash }
        deliveryDate={ deliveryDate }
        availabilities={ availabilities }
        items={ items }
        itemsTotal={ itemsTotal }
        total={ total }
        adjustments={ adjustments }
        isMobileCart={ isXsDevice }
        datePickerDateInputName={ this.options.datePickerDateInputName }
        datePickerTimeInputName={ this.options.datePickerTimeInputName }
        onDateChange={ date => this._onDateChange(date) }
        onAddressChange={ streetAddress => this._onAddressChange(streetAddress) }
        onRemoveItem={ item => this.removeCartItem(item) }
        onClickCartReset={ () => this.reset() }
        onClickGoBack={ () => this.gotoCartRestaurantURL() } />, el, onRender)
  }

  initTop(el, cart) {
    render(
      <CartTop
        restaurant={ cart.restaurant }
        total={ cart.total }
        itemsTotal={ cart.itemsTotal }
      />, el)
  }

  _setLoading(loading) {
    this.cartComponentRef.current.setLoading(loading)
  }

  _onDateChange(date) {
    window._paq.push(['trackEvent', 'Checkout', 'changeDate']);
  }

  _mapAddressToElements(address) {
    _.forEach(this.options.addressFormElements, (el, key) => {
      if (address.hasOwnProperty(key)) {
        el.value = address[key]
      }
    })
  }

  _onAddressChange(address) {

    window._paq.push(['trackEvent', 'Checkout', 'changeAddress', address.streetAddress]);

    // If the address is not precise, we do not save it
    if (!address.isPrecise) {
      this.cartComponentRef.current.setErrors({
        shippingAddress: [
          i18n.t('CART_ADDRESS_NOT_ENOUGH_PRECISION')
        ]
      })
    } else {
      this._mapAddressToElements(address)
      this._postCart()
    }
  }

  _postCart() {

    this._setLoading(true)

    const data = $('form[name="cart"]').serializeArray()

    $.post($('form[name="cart"]').attr('action'), data)
      .then(res => this.handleAjaxResponse(res))
      .fail(e => this.handleAjaxResponse(e.responseJSON))
  }

  handleAjaxResponse(res) {

    this._setLoading(false)

    const { availabilities, cart, errors } = res

    this.cartComponentRef.current.setAvailabilities(availabilities)
    this.cartComponentRef.current.setCart(cart)
    this.cartComponentRef.current.setErrors(errors)

    _.each(errors, (value, key) => window._paq.push(['trackEvent', 'Checkout', 'showError', key]))

    const event = new CustomEvent('cart:change', { detail: cart })
    document.querySelectorAll('[data-cart-listener]').forEach(listener => listener.dispatchEvent(event))
  }

  addProduct(url, quantity) {
    window._paq.push(['trackEvent', 'Checkout', 'addItem']);
    this._setLoading(true)
    $.post(url, {
      quantity: quantity
    })
    .then(res => this.handleAjaxResponse(res))
    .fail(e => this.handleAjaxResponse(e.responseJSON))
  }

  addProductWithOptions(url, data, quantity) {
    window._paq.push(['trackEvent', 'Checkout', 'addItemWithOptions']);
    this._setLoading(true)
    data.push({
      name: 'quantity',
      value: quantity
    })
    $.post(url, data)
      .then(res => this.handleAjaxResponse(res))
      .fail(e => this.handleAjaxResponse(e.responseJSON))
  }

  removeCartItem(item) {
    window._paq.push(['trackEvent', 'Checkout', 'removeItem']);
    this._setLoading(true)
    $.ajax({
      url: window.Routing.generate('restaurant_remove_from_cart', {
        id: this.options.restaurant.id,
        cartItemId: item.id
      }),
      type: 'DELETE',
    })
    .then(res => this.handleAjaxResponse(res))
    .fail(e => this.handleAjaxResponse(e.responseJSON))
  }

  gotoCartRestaurantURL() {
    window.location.href = this.options.cartRestaurantURL
  }

  reset() {
    this._setLoading(true)
    $.post(this.options.resetCartURL)
      .then(res => this.handleAjaxResponse(res))
      .fail(e => this.handleAjaxResponse(e.responseJSON))
  }
}

window.CoopCycle.Cart = CartHelper
