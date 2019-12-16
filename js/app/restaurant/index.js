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

window.initMap = function() {

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
  })

  $('form[data-product-options] input[type="checkbox"]').on('click', function() {
    window._paq.push(['trackEvent', 'Checkout', 'addExtra'])
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
  let cart = JSON.parse(restaurantDataElement.dataset.cart)
  const times = JSON.parse(restaurantDataElement.dataset.times)

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
    addresses,
    times
  }

  store = createStoreFromPreloadedState(state)

  const container = document.getElementById('cart')

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
