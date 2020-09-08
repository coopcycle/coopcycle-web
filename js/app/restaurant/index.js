import React from 'react'
import { render } from 'react-dom'
import { Provider } from 'react-redux'
import { I18nextProvider } from 'react-i18next'
import Modal from 'react-modal'
import _ from 'lodash'

import engine  from 'store/src/store-engine'
import session from 'store/storages/sessionStorage'
import cookie  from 'store/storages/cookieStorage'

import OpeningHoursParser from '../widgets/OpeningHoursParser'
import i18n, { getCountry } from '../i18n'
import { createStoreFromPreloadedState } from '../cart/redux/store'
import { addItem, addItemWithOptions, queueAddItem } from '../cart/redux/actions'
import Cart from '../cart/components/Cart'
import { validateForm } from '../utils/address'

require('gasparesganga-jquery-loading-overlay')

import './index.scss'

const storage = engine.createStore([ session, cookie ])

window._paq = window._paq || []

let store

function isValid($form) {
  const $optionGroups = $form.find('[data-product-options-group]')
  const $validOptionGroups = $optionGroups.filter((index, el) => validateRange($(el)))

  return $optionGroups.length === $validOptionGroups.length
}

function resetForm($form) {
  $form.find('input[type="radio"]:checked').prop('checked', false)
  $form.find('input[type="checkbox"]:checked').prop('checked', false)

  $form.find('[data-stepper]').prop('disabled', false)

  $form.find('input[type="number"]').each(function () {
    $(this).prop('disabled', false)
    $(this).val($(this).attr('min'))
  })
  $form.find('[data-product-options-group]').children().removeClass('disabled')
}

function asRange($el) {
  let min = $el.attr('data-product-options-group-min')
  let max = $el.attr('data-product-options-group-max')

  min = min ? parseInt(min, 10) : 0
  max = max ? parseInt(max, 10) : Infinity

  return [ min, max ]
}

function asOptionsCount($el) {
  const radios = $el.find('input[type="radio"]:checked').length
  const checkboxes = $el.find('input[type="checkbox"]:checked').length
  const numbers = $el
    .find('input[type="number"]')
    .map((index, el) => parseInt($(el).val(), 10))
    .get()
    .reduce((acc, val) => acc + val, 0)

  return radios + checkboxes + numbers
}

function validateRange($el) {
  const [ min, max ] = asRange($el)
  const optionsCount = asOptionsCount($el)

  if (optionsCount < min) {
    return false
  }

  if (max !== Infinity && optionsCount > max) {
    return false
  }

  return true
}

function updateTotal($form) {

  const $optionsGroups = $form.find('[data-product-options-group]')

  const totalPerUnit = $optionsGroups.toArray().reduce((total, optionsGroup) => {

    const subTotal = $(optionsGroup).children().toArray().reduce((subTotal, el) => {

      const $radioOrCheckbox = $(el).find('input[type="radio"],input[type="checkbox"]')
      const isChecked = $radioOrCheckbox.length === 1 ? $radioOrCheckbox.is(':checked') : true

      if (!isChecked) {

        return subTotal
      }

      const $quantity = $(el).find('input[type="number"]')
      const quantity = $quantity.length === 1 ? parseInt($quantity.val(), 10) : 1
      const price = $(el).data('option-value-price')

      return subTotal + (price * quantity)

    }, 0)

    return total + subTotal

  }, $form.data('product-price'))

  const $quantity = $form.find('[data-product-quantity]')
  const quantity = parseInt($quantity.val(), 10)
  const total = totalPerUnit * quantity

  $form
    .find('[data-product-total]')
    .text((total / 100).formatMoney())
}

window.initMap = function() {

  const container = document.getElementById('cart')

  if (!container) {

    return
  }

  $('form[data-product-simple]').on('submit', function(e) {
    e.preventDefault()
    store.dispatch(queueAddItem($(this).attr('action'), 1))
  })

  // Make sure all (non-additional) options have been checked
  $('form[data-product-options] input[type="radio"]').on('change', function() {
    const $form = $(this).closest('form')
    window._paq.push(['trackEvent', 'Checkout', 'selectOption'])
    if (isValid($form)) {
      $form.find('button[type="submit"]').removeAttr('disabled')
    }
    updateTotal($form)
  })

  $('form[data-product-options] input[type="checkbox"]').on('click', function() {
    const $form = $(this).closest('form')
    window._paq.push(['trackEvent', 'Checkout', 'addExtra'])
    updateTotal($form)
  })

  $('button[data-stepper]').on('click', function(e) {
    e.preventDefault()

    const $input = $($(this).data('target'))
    const direction = $(this).data('direction')

    if (direction === 'down') {
      $input[0].stepDown()
      $input.trigger('change')
    }
    if (direction === 'up') {
      $input[0].stepUp()
      $input.trigger('change')
    }
  })

  $('label[data-step-up]').on('click', function(e) {
    e.preventDefault()
    const $input = $('#' + $(this).attr('for'))
    if (!$input.prop('disabled')) {
      $input[0].stepUp()
      $input.trigger('change')
    }
  })

  $('form[data-product-options] [data-product-options-group] input[type="number"]').on('change', function() {

    window._paq.push(['trackEvent', 'Checkout', 'addExtra'])

    const $form = $(this).closest('form')
    const $optionsGroup = $(this).closest('[data-product-options-group]')

    if (isValid($form)) {
      $form.find('button[type="submit"]').removeAttr('disabled')
    } else {
      $form.find('button[type="submit"]').prop('disabled', true)
    }

    const optionsCount = asOptionsCount($optionsGroup)
    const range = asRange($optionsGroup)
    const max = range[1]

    if (max !== Infinity && optionsCount === max) {
      $optionsGroup.children().each(function () {
        const $code = $(this).find('input[type="hidden"]')
        const $quantity = $(this).find('input[type="number"]')
        if (parseInt($quantity.val(), 10) === 0) {
          $code.prop('disabled', true)
          $quantity.prop('disabled', true)
          $(this).addClass('disabled')
        }
        $(this).find('[data-stepper][data-direction="up"]').prop('disabled', true)
      })
    } else {
      $optionsGroup.children().each(function () {
        const $code = $(this).find('input[type="hidden"]')
        const $quantity = $(this).find('input[type="number"]')
        $code.prop('disabled', false)
        $quantity.prop('disabled', false)
        if (max !== Infinity) {
          $quantity.attr('max', max - (optionsCount - parseInt($quantity.val(), 10)))
        }
        $(this).find('[data-stepper][data-direction="up"]').prop('disabled', false)
        $(this).removeClass('disabled')
      })
    }

    updateTotal($form)
  })

  $('form[data-product-options] [data-product-quantity]').on('change', function() {
    updateTotal($(this).closest('[data-product-options]'))
  })

  $('form[data-product-options]').on('submit', function(e) {
    e.preventDefault()
    const data = $(this).serializeArray()
    const quantity = $(this).find('[data-product-quantity]').val() || 1

    if (data.length > 0) {
      store.dispatch(addItemWithOptions($(this).attr('action'), data, quantity))
    } else {
      store.dispatch(addItem($(this).attr('action'), quantity))
    }

    $(this).closest('.modal').modal('hide')
    resetForm($(this).closest('form'))
  })

  // We update the base price on "show.bs.modal" to avoid flickering
  $('.modal').on('show.bs.modal', function() {
    var $form = $(this).find('form[data-product-options]')
    if ($form.length === 1) {
      const price = $form.data('product-price')
      $form
        .find('button[type="submit"] [data-total]')
        .text((price / 100).formatMoney())
    }
  })

  $('.modal').on('shown.bs.modal', function() {
    var $form = $(this).find('form[data-product-options]')
    if ($form.length === 1) {
      window._paq.push(['trackEvent', 'Checkout', 'showOptions'])
      $form.find('button[type="submit"]').prop('disabled', !isValid($form))
    }
  })

  $('.modal').on('hidden.bs.modal', function() {
    window._paq.push(['trackEvent', 'Checkout', 'hideOptions'])
  })

  const restaurantDataElement = document.querySelector('#js-restaurant-data')
  const addressesDataElement = document.querySelector('#js-addresses-data')

  const restaurant = JSON.parse(restaurantDataElement.dataset.restaurant)
  const times = JSON.parse(restaurantDataElement.dataset.times)
  const addresses = JSON.parse(addressesDataElement.dataset.addresses)

  document.querySelectorAll('[data-opening-hours]').forEach(el => {
    // FIXME Check parse errors
    new OpeningHoursParser(el, {
      openingHours: JSON.parse(el.dataset.openingHours),
      locale: $('html').attr('lang'),
      behavior: el.dataset.openingHoursBehavior,
    })
  })

  let cart = JSON.parse(restaurantDataElement.dataset.cart)

  if (!cart.shippingAddress) {

    const searchAddress = storage.get('search_address')

    if (_.isObject(searchAddress)) {
      cart = {
        ...cart,
        shippingAddress: {
          ...searchAddress
        }
      }
    } else {
      cart = {
        ...cart,
        shippingAddress: {
          streetAddress: searchAddress
        }
      }
    }
  }

  $(container).closest('form').on('submit', function (e) {

    const searchInput = document.querySelector('#cart input[type="search"]')
    const latInput = document.querySelector('#cart_shippingAddress_latitude')
    const lngInput = document.querySelector('#cart_shippingAddress_longitude')
    const streetAddrInput = document.querySelector('#cart_shippingAddress_streetAddress')

    validateForm(e, searchInput, latInput, lngInput, streetAddrInput)
  })

  const state = {
    cart,
    restaurant,
    datePickerTimeSlotInputName: 'cart[timeSlot]',
    addressFormElements: {
      'streetAddress': document.querySelector('#cart_shippingAddress_streetAddress'),
      'postalCode': document.querySelector('#cart_shippingAddress_postalCode'),
      'addressLocality': document.querySelector('#cart_shippingAddress_addressLocality'),
      'geo.latitude': document.querySelector('#cart_shippingAddress_latitude'),
      'geo.longitude': document.querySelector('#cart_shippingAddress_longitude')
    },
    isNewAddressFormElement: document.querySelector('#cart_isNewAddress'),
    addresses,
    times,
    country: getCountry(),
  }

  store = createStoreFromPreloadedState(state)

  Modal.setAppElement(container)

  render(
    <Provider store={ store }>
      <I18nextProvider i18n={ i18n }>
        <Cart />
      </I18nextProvider>
    </Provider>,
    container
  )

}

$('#menu').LoadingOverlay('show', {
  image: false,
})
