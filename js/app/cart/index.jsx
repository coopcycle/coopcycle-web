import React from 'react'
import { render, findDOMNode } from 'react-dom'
import Cart from './Cart.jsx'
import CartTop from './CartTop.jsx'
import moment from 'moment'
import { geocodeByAddress } from 'react-places-autocomplete'
import Promise from 'promise'

let isXsDevice = $('.visible-xs').is(':visible')

window.CoopCycle = window.CoopCycle || {}

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

    const geohash = localStorage.getItem('search_geohash') || ''
    const availabilities = options.restaurant.availabilities
    const streetAddress = shippingAddress ? shippingAddress.streetAddress : (localStorage.getItem('search_address') || '')
    let deliveryDate = date || localStorage.getItem('search__date') || options.restaurant.availabilities[0]
    deliveryDate = _.find(availabilities, (date) => moment(deliveryDate).isSame(date)) ? deliveryDate : options.restaurant.availabilities[0]

    this.cartComponentRef = React.createRef()

    const onRender = () => {
      const cart = this.cartComponentRef.current.getCart()
      if (cart.address) {
        this
          ._geocode(cart.address)
          .then(address => this.updateCart({ address, date: cart.date }))
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
        onDateChange={ date => this.updateCart({ date }) }
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
            reject()
            return
          }

          // format Google's places format to a clean dict
          let place = results[0],
            addressDict = {},
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

  _onAddressChange(streetAddress) {
    this
      ._geocode(streetAddress)
      .then(address => this.updateCart({ address }))
  }

  handleAjaxResponse(res) {

    this.cartComponentRef.current.setLoading(false)

    const { cart, errors } = res

    if (errors.hasOwnProperty('restaurant')) {
      this.options.onCartWarning()
    } else {
      this.cartComponentRef.current.setCart(cart)
      this.cartComponentRef.current.setErrors(errors)

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
    this.cartComponentRef.current.setLoading(true)
    $.post(url, {
      quantity: quantity
    })
    .then(res => this.handleAjaxResponse(res))
    .fail(e => this.handleAjaxResponse(e.responseJSON))
  }

  addProductWithOptions(url, data, quantity) {
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
    this.cartComponentRef.current.setLoading(true)
    $.ajax({
      url: this.options.removeFromCartURL.replace('__CART_ITEM_ID__', item.id),
      type: 'DELETE',
    })
    .then(res => this.handleAjaxResponse(res))
    .fail(e => this.handleAjaxResponse(e.responseJSON))
  }

  reset() {
    $.post(this.options.resetCartURL)
      .then(res => this.handleAjaxResponse(res))
      .fail(e => this.handleAjaxResponse(e.responseJSON))
  }
}

window.CoopCycle.Cart = CartHelper
