import React from 'react'
import { render, findDOMNode } from 'react-dom'
import Cart from './Cart.jsx'
import CartTop from './CartTop.jsx'
import i18n from '../i18n'
import moment from 'moment'
import _ from 'lodash'
import { geocodeByAddress } from 'react-places-autocomplete'
import Promise from 'promise'

let isXsDevice = $('.visible-xs').is(':visible')

window.CoopCycle = window.CoopCycle || {}
window._paq = window._paq || []

const NO_RESULTS = 'NO_RESULTS'
const NOT_ENOUGH_PRECISION = 'NOT_ENOUGH_PRECISION'

class CartHelper {

  constructor(options) {
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
      const cart = this.cartComponentRef.current.getCart()
      if (cart.address) {
        this
          ._geocode(cart.address)
          .then(address => this.updateCart({ address, date: cart.date }))
          .catch(e => this._handleGeocodeError(e))
      } else {
        this.updateCart({ date: cart.date })
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
        validateCartURL={ this.options.validateCartURL }
        onDateChange={ date => this._onDateChange(date) }
        onAddressChange={ streetAddress => this._onAddressChange(streetAddress) }
        onRemoveItem={ item => this.removeCartItem(item) } />, el, onRender)
  }

  initTop(el, cart) {
    render(
      <CartTop
        restaurantURL={ this.options.restaurantURL }
        restaurant={ cart.restaurant }
        total={ cart.total }
        itemsTotal={ cart.itemsTotal }
      />, el)
  }

  _geocode(streetAddress) {
    return new Promise((resolve, reject) => {
      geocodeByAddress(streetAddress)
        .then(results => {

          if (results.length === 0) {
            reject(NO_RESULTS)
            return
          }

          const place = results[0]

          // Make sure we have a "precise" street address
          // Basically, we make sure we have a street number
          // Do not use place.types, as this may return a variety of types
          // @see https://developers.google.com/places/supported_types
          const hasStreetNumber =
            Boolean(_.find(place.address_components, component => _.includes(component['types'], 'street_number')))

          if (!hasStreetNumber) {
            reject(NOT_ENOUGH_PRECISION)
            return
          }

          // format Google's places format to a clean dict
          let addressDict = {},
            lat = place.geometry.location.lat(),
            lng = place.geometry.location.lng();

          place.address_components.forEach(function (item) {
            addressDict[item.types[0]] = item.long_name
          });

          addressDict.streetAddress = addressDict.street_number ? addressDict.street_number + ' ' + addressDict.route : addressDict.route;

          resolve({
            'latitude': lat,
            'longitude': lng,
            'addressCountry': addressDict.country || '',
            'addressLocality': addressDict.locality || '',
            'addressRegion': addressDict.administrative_area_level_1 || '',
            'postalCode': addressDict.postal_code || '',
            'streetAddress': addressDict.streetAddress || '',
          })

        })
        .catch(e => reject(e))
    })
  }

  _onDateChange(date) {
    window._paq.push(['trackEvent', 'Checkout', 'changeDate']);
    this.updateCart({ date })
  }

  _onAddressChange(streetAddress) {
    window._paq.push(['trackEvent', 'Checkout', 'changeAddress', streetAddress]);
    this
      ._geocode(streetAddress)
      .then(address => this.updateCart({ address }))
      .catch(e => this._handleGeocodeError(e))
  }

  _handleGeocodeError(e) {
    if (e === NOT_ENOUGH_PRECISION) {
      this.cartComponentRef.current.setErrors({
        shippingAddress: [
          i18n.t('CART_ADDRESS_NOT_ENOUGH_PRECISION')
        ]
      })
    }
  }

  handleAjaxResponse(res) {

    this.cartComponentRef.current.setLoading(false)

    const { cart, errors } = res

    if (errors.hasOwnProperty('restaurant')) {
      this.options.onCartWarning()
    } else {
      this.cartComponentRef.current.setCart(cart)
      this.cartComponentRef.current.setErrors(errors)

      _.each(errors, (value, key) => window._paq.push(['trackEvent', 'Checkout', 'showError', key]))

      const event = new CustomEvent('cart:change', { detail: cart })
      document.querySelectorAll('[data-cart-listener]').forEach(listener => listener.dispatchEvent(event))
    }
  }

  updateCart(payload) {
    this.cartComponentRef.current.setLoading(true)
    $.post(this.options.cartURL, payload)
      .then(res => this.handleAjaxResponse(res))
      .fail(e => this.handleAjaxResponse(e.responseJSON))
  }

  addProduct(url, quantity) {
    window._paq.push(['trackEvent', 'Checkout', 'addItem']);
    this.cartComponentRef.current.setLoading(true)
    $.post(url, {
      quantity: quantity
    })
    .then(res => this.handleAjaxResponse(res))
    .fail(e => this.handleAjaxResponse(e.responseJSON))
  }

  addProductWithOptions(url, data, quantity) {
    window._paq.push(['trackEvent', 'Checkout', 'addItemWithOptions']);
    this.cartComponentRef.current.setLoading(true)
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
    this.cartComponentRef.current.setLoading(true)
    $.ajax({
      url: this.options.removeFromCartURL.replace('__CART_ITEM_ID__', item.id),
      type: 'DELETE',
    })
    .then(res => this.handleAjaxResponse(res))
    .fail(e => this.handleAjaxResponse(e.responseJSON))
  }

  reset() {
    this.cartComponentRef.current.setLoading(true)
    $.post(this.options.resetCartURL)
      .then(res => this.handleAjaxResponse(res))
      .fail(e => this.handleAjaxResponse(e.responseJSON))
  }
}

window.CoopCycle.Cart = CartHelper
