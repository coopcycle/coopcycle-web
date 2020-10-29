import React from 'react'
import { render, unmountComponentAtNode } from 'react-dom'
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
import ProductOptionsModal from './components/ProductOptionsModal'
import ProductDetailsModal from './components/ProductDetailsModal'

require('gasparesganga-jquery-loading-overlay')

import './index.scss'

const storage = engine.createStore([ session, cookie ])

window._paq = window._paq || []

let store

window.initMap = function() {

  const container = document.getElementById('cart')

  if (!container) {

    return
  }

  $('form[data-product-simple]').on('submit', function(e) {
    e.preventDefault()
    $(e.currentTarget).closest('.modal').modal('hide')
    store.dispatch(queueAddItem($(this).attr('action'), 1))
  })

  // We update the base price on "show.bs.modal" to avoid flickering
  $('#product-options').on('show.bs.modal', function(event) {

    const $modal = $(this)
    $modal.find('.modal-title').text(event.relatedTarget.dataset.productName)

    const productOptions =
      JSON.parse(event.relatedTarget.dataset.productOptions)

    render(
      <ProductOptionsModal
        price={ JSON.parse(event.relatedTarget.dataset.productPrice) }
        code={ event.relatedTarget.dataset.productCode }
        options={ productOptions }
        formAction={ event.relatedTarget.dataset.formAction }
        onSubmit={ (e) => {
          e.preventDefault()

          const $form = $modal.find('form')

          const data = $form.serializeArray()
          const quantity = $form.find('[data-product-quantity]').val() || 1

          if (data.length > 0) {
            store.dispatch(addItemWithOptions($form.attr('action'), data, quantity))
          } else {
            store.dispatch(addItem($form.attr('action'), quantity))
          }

          $modal.modal('hide')

        } } />,
      this.querySelector('.modal-body')
    )
  })

  $('#product-options').on('shown.bs.modal', function() {
    var $form = $(this).find('form[data-product-options]')
    if ($form.length === 1) {
      window._paq.push(['trackEvent', 'Checkout', 'showOptions'])
      // $form.find('button[type="submit"]').prop('disabled', !isValid($form))
    }
  })

  $('#product-options').on('hidden.bs.modal', function() {
    unmountComponentAtNode(this.querySelector('.modal-body'))
    window._paq.push(['trackEvent', 'Checkout', 'hideOptions'])
  })

  $('#product-details').on('show.bs.modal', function(event) {

    const images = JSON.parse(event.relatedTarget.dataset.productImages)
    const productPrice = JSON.parse(event.relatedTarget.dataset.productPrice)

    const $modal = $(this)
    $modal.find('.modal-title').text(event.relatedTarget.dataset.productName)
    $modal.find('form').attr('action', event.relatedTarget.dataset.formAction)
    $modal.find('button[type="submit"]').text((productPrice / 100).formatMoney())

    const $placeholder = $('<div>')
    $placeholder.addClass('d-flex')
    $placeholder.addClass('overflow-hidden')
    images.forEach(image => {
      const $img = $('<img>')
      $img.attr('src', image)
      $placeholder.append($img)
    })
    $('.modal-body [data-swiper]').append($placeholder)
  })

  $('#product-details').on('shown.bs.modal', function(event) {
    const images = JSON.parse(event.relatedTarget.dataset.productImages)
    render(
      <ProductDetailsModal images={ images } />,
      this.querySelector('.modal-body [data-swiper]')
    )
  })

  $('#product-details').on('hidden.bs.modal', function() {
    unmountComponentAtNode(this.querySelector('.modal-body [data-swiper]'))
  })

  const restaurantDataElement = document.querySelector('#js-restaurant-data')
  const addressesDataElement = document.querySelector('#js-addresses-data')

  const restaurant = JSON.parse(restaurantDataElement.dataset.restaurant)
  const times = JSON.parse(restaurantDataElement.dataset.times)
  const addresses = JSON.parse(addressesDataElement.dataset.addresses)

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

document.querySelectorAll('[data-opening-hours]').forEach(el => {
  // FIXME Check parse errors
  new OpeningHoursParser(el, {
    openingHours: JSON.parse(el.dataset.openingHours),
    locale: $('html').attr('lang'),
    behavior: el.dataset.openingHoursBehavior,
  })
})

$('#menu').LoadingOverlay('show', {
  image: false,
})
