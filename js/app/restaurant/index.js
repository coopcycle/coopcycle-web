import React from 'react'
import { render } from 'react-dom'
import moment from 'moment'
import _ from 'lodash'
import { geocodeByAddress } from 'react-places-autocomplete'

import Cart from '../cart/Cart.jsx'
import OpeningHoursParser from '../widgets/OpeningHoursParser'
import i18n from '../i18n'
import { placeToAddress } from '../utils/GoogleMaps'

require('gasparesganga-jquery-loading-overlay')

let isXsDevice = $('.visible-xs').is(':visible')

window.CoopCycle = window.CoopCycle || {}
window._paq = window._paq || []

let timeoutID = null

class CartFacade {

  constructor(options = {}) {
    this.options = options
    this.isInitialized = false
  }

  render(el, onLoad) {

    const {
      adjustments,
      date,
      items,
      itemsTotal,
      shippingAddress,
      total
    } = this.options.cart

    const geohash = sessionStorage.getItem('search_geohash') || ''
    const availabilities = this.options.restaurant.availabilities

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

      onLoad()

      this.isInitialized = true
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

  _setLoading(loading) {
    this.cartComponentRef.current.setLoading(loading)
  }

  _isLoading() {
    return this.cartComponentRef.current.isLoading()
  }

  _isInitialized() {
    return this.isInitialized
  }

  _onDateChange(date) {
    window._paq.push(['trackEvent', 'Checkout', 'changeDate']);
    this._postCart();
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

    const waitFor = () => {
      if (this._isInitialized() && !this._isLoading()) {

        this._setLoading(true)

        $.post(url, {
          quantity: quantity
        })
        .then(res => this.handleAjaxResponse(res))
        .fail(e => this.handleAjaxResponse(e.responseJSON))

        clearTimeout(timeoutID)
      } else {
        timeoutID = setTimeout(waitFor, 100)
      }
    }

    waitFor()
  }

  addProductWithOptions(url, data, quantity) {
    window._paq.push(['trackEvent', 'Checkout', 'addItemWithOptions']);

    const waitFor = () => {
      if (this._isInitialized() && !this._isLoading()) {

        this._setLoading(true)

        data.push({
          name: 'quantity',
          value: quantity
        })

        $.post(url, data)
          .then(res => this.handleAjaxResponse(res))
          .fail(e => this.handleAjaxResponse(e.responseJSON))

        clearTimeout(timeoutID)
      } else {
        timeoutID = setTimeout(waitFor, 100)
      }
    }

    waitFor()
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
    window.location.href = window.Routing.generate('restaurant', {
      id: this.options.cart.restaurant.id
    })
  }

  reset() {
    this._setLoading(true)
    const resetCartURL = window.Routing.generate('restaurant_cart_reset', { id: this.options.restaurant.id })
    $.post(resetCartURL)
      .then(res => this.handleAjaxResponse(res))
      .fail(e => this.handleAjaxResponse(e.responseJSON))
  }
}

window.initMap = function() {

  let CartHelper

  $('form[data-product-simple]').on('submit', function(e) {
    e.preventDefault();
    CartHelper.addProduct($(this).attr('action'), 1);
  });

  // Make sure all (non-additional) options have been checked
  $('form[data-product-options] input[type="radio"]').on('change', function(e) {

    var $options = $(this).closest('form').find('[data-product-option]');
    var checkedOptionsCount = 0;
    $options.each(function(index, el) {
      checkedOptionsCount += $(el).find('input[type="radio"]:checked').length;
    });

    _paq.push(['trackEvent', 'Checkout', 'selectOption']);

    if ($options.length === checkedOptionsCount) {
      $(this).closest('form').find('button[type="submit"]').prop('disabled', false);
      $(this).closest('form').find('button[type="submit"]').removeAttr('disabled');
    }
  });

  $('form[data-product-options] input[type="checkbox"]').on('click', function(e) {
    _paq.push(['trackEvent', 'Checkout', 'addExtra']);
  });

  $('form[data-product-options]').on('submit', function(e) {
    e.preventDefault();
    var data = $(this).serializeArray();
    if (data.length > 0) {
      CartHelper.addProductWithOptions($(this).attr('action'), data, 1);
    } else {
      CartHelper.addProduct($(this).attr('action'), 1);
    }

    $(this).closest('.modal').modal('hide');
    // Uncheck all options
    $(this).closest('form').find('input[type="radio"]:checked').prop('checked', false);
    $(this).closest('form').find('input[type="checkbox"]:checked').prop('checked', false);
  });

  $('.modal').on('shown.bs.modal', function(e) {
    _paq.push(['trackEvent', 'Checkout', 'showOptions']);
    var $form = $(this).find('form[data-product-options]');
    if ($form.length === 1) {
      var $options = $form.find('[data-product-option]');
      var disabled = $options.length > 0;
      $form.find('button[type="submit"]').prop('disabled', disabled);
    }
  });

  $('.modal').on('hidden.bs.modal', function(e) {
    _paq.push(['trackEvent', 'Checkout', 'hideOptions']);
  });

  const restaurantDataElement = document.querySelector('#js-restaurant-data')

  const restaurant = JSON.parse(restaurantDataElement.dataset.restaurant)
  const cart = JSON.parse(restaurantDataElement.dataset.cart)

  new OpeningHoursParser(document.querySelector('#opening-hours'), {
    openingHours: restaurant.openingHours,
    locale: $('html').attr('lang')
  })

  CartHelper = new CartFacade({
    restaurant: restaurant,
    cart: cart,
    datePickerDateInputName: "cart[date]",
    datePickerTimeInputName: "cart[time]",
    addressFormElements: {
      streetAddress: document.querySelector("#cart_shippingAddress_streetAddress"),
      postalCode: document.querySelector("#cart_shippingAddress_postalCode"),
      addressLocality: document.querySelector("#cart_shippingAddress_addressLocality"),
      latitude: document.querySelector("#cart_shippingAddress_latitude"),
      longitude: document.querySelector("#cart_shippingAddress_longitude")
    }
  })

  CartHelper.render(document.querySelector('#cart'), function() {
    document.querySelector('#cart').setAttribute('data-ready', 'true')
    $('#menu').LoadingOverlay('hide')
  })

}

$('#menu').LoadingOverlay('show', {
  image: false,
})
