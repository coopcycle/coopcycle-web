import React from 'react'
import { render } from 'react-dom'
import { Provider } from 'react-redux'
import { I18nextProvider } from 'react-i18next'
import Modal from 'react-modal'

import OpeningHoursParser from '../widgets/OpeningHoursParser'
import i18n from '../i18n'
import { createStoreFromPreloadedState } from '../cart/redux/store'
import { addItem, addItemWithOptions, queueAddItem } from '../cart/redux/actions'
import Cart from '../cart/components/Cart'

require('gasparesganga-jquery-loading-overlay')

window._paq = window._paq || []

Modal.setAppElement(document.getElementById('cart'))

let store

window.initMap = function() {

  $('[data-quantity-decrement]').on('click', function(e) {
    e.preventDefault()
    const $quantity = $(this).closest('.quantity-input-group').find('[data-quantity]')
    const currentQuantity = parseInt($quantity.val(), 10)
    if (currentQuantity > 1) {
      $quantity.val(currentQuantity - 1)
    }
  })

  $('[data-quantity-increment]').on('click', function(e) {
    e.preventDefault()
    const $quantity = $(this).closest('.quantity-input-group').find('[data-quantity]')
    const currentQuantity = parseInt($quantity.val(), 10)
    $quantity.val(currentQuantity + 1)
  })

  $('form[data-product-simple]').on('submit', function(e) {
    e.preventDefault()
    store.dispatch(queueAddItem($(this).attr('action'), 1))
  })

  // Make sure all (non-additional) options have been checked
  $('form[data-product-options] input[type="radio"]').on('change', function() {

    var $options = $(this).closest('form').find('[data-product-option]')
    var checkedOptionsCount = 0
    $options.each(function(index, el) {
      checkedOptionsCount += $(el).find('input[type="radio"]:checked').length
    })

    window._paq.push(['trackEvent', 'Checkout', 'selectOption'])

    if ($options.length === checkedOptionsCount) {
      $(this).closest('form').find('button[type="submit"]').prop('disabled', false)
      $(this).closest('form').find('button[type="submit"]').removeAttr('disabled')
    }
  })

  $('form[data-product-options] input[type="checkbox"]').on('click', function() {
    window._paq.push(['trackEvent', 'Checkout', 'addExtra'])
  })

  $('form[data-product-options]').on('submit', function(e) {
    e.preventDefault()
    const data = $(this).serializeArray()
    const $quantity = $(this).find('[data-quantity]')
    const quantity = $quantity.val() || 1

    if (data.length > 0) {
      store.dispatch(addItemWithOptions($(this).attr('action'), data, quantity))
    } else {
      store.dispatch(addItem($(this).attr('action'), quantity))
    }

    $(this).closest('.modal').modal('hide')
    // Uncheck all options
    $(this).closest('form').find('input[type="radio"]:checked').prop('checked', false)
    $(this).closest('form').find('input[type="checkbox"]:checked').prop('checked', false)
    // Reset quantity
    $quantity.val(1)
  })

  $('.modal').on('shown.bs.modal', function() {
    window._paq.push(['trackEvent', 'Checkout', 'showOptions'])
    var $form = $(this).find('form[data-product-options]')
    if ($form.length === 1) {
      var $options = $form.find('[data-product-option]')
      var disabled = $options.length > 0
      $form.find('button[type="submit"]').prop('disabled', disabled)
    }
  })

  $('.modal').on('hidden.bs.modal', function() {
    window._paq.push(['trackEvent', 'Checkout', 'hideOptions'])
  })

  const restaurantDataElement = document.querySelector('#js-restaurant-data')
  const addressesDataElement = document.querySelector('#js-addresses-data')

  const restaurant = JSON.parse(restaurantDataElement.dataset.restaurant)
  let cart = JSON.parse(restaurantDataElement.dataset.cart)

  const addresses = JSON.parse(addressesDataElement.dataset.addresses)

  // FIXME Check parse errors

  new OpeningHoursParser(document.querySelector('#opening-hours'), {
    openingHours: restaurant.openingHours,
    locale: $('html').attr('lang')
  })

  if (!cart.shippingAddress) {
    const searchAddress = sessionStorage.getItem('search_address')
    if (searchAddress) {
      cart = {
        ...cart,
        shippingAddress: {
          streetAddress: searchAddress
        }
      }
    }
  }

  const state = {
    cart,
    restaurant,
    availabilities: restaurant.availabilities,
    datePickerDateInputName: 'cart[date]',
    datePickerTimeInputName: 'cart[time]',
    addressFormElements: {
      streetAddress: document.querySelector('#cart_shippingAddress_streetAddress'),
      postalCode: document.querySelector('#cart_shippingAddress_postalCode'),
      addressLocality: document.querySelector('#cart_shippingAddress_addressLocality'),
      latitude: document.querySelector('#cart_shippingAddress_latitude'),
      longitude: document.querySelector('#cart_shippingAddress_longitude')
    },
    isNewAddressFormElement: document.querySelector('#cart_isNewAddress'),
    addresses
  }

  store = createStoreFromPreloadedState(state)

  render(
    <Provider store={ store }>
      <I18nextProvider i18n={ i18n }>
        <Cart />
      </I18nextProvider>
    </Provider>,
    document.querySelector('#cart'),
    () => {
      document.querySelector('#cart').setAttribute('data-ready', 'true')
      $('#menu').LoadingOverlay('hide')
    }
  )

}

$('#menu').LoadingOverlay('show', {
  image: false,
})
