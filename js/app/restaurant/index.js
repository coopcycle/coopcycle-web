import React from 'react'
import { render } from 'react-dom'
import { Provider } from 'react-redux'
import { I18nextProvider } from 'react-i18next'
import Modal from 'react-modal'
import _ from 'lodash'

import i18n, { getCountry } from '../i18n'
import { createStoreFromPreloadedState } from '../cart/redux/store'
import { queueAddItem, openProductOptionsModal, openProductDetailsModal } from '../cart/redux/actions'
import Cart from '../cart/components/Cart'
import storage from '../search/address-storage'

require('gasparesganga-jquery-loading-overlay')

import './index.scss'

window._paq = window._paq || []

let store

const init = function() {

  const container = document.getElementById('cart')

  if (!container) {

    return
  }

  $('form[data-product-simple]').on('submit', function(e) {
    e.preventDefault()
    $(e.currentTarget).closest('.modal').modal('hide')
    store.dispatch(queueAddItem($(this).attr('action'), 1))
  })

  document.querySelectorAll('[data-modal="options"]').forEach(el => {
    el.addEventListener('click', () => {

      const name       = el.dataset.productName
      const options    = JSON.parse(el.dataset.productOptions)
      const images     = JSON.parse(el.dataset.productImages)
      const price      = JSON.parse(el.dataset.productPrice)
      const code       = el.dataset.productCode
      const formAction = el.dataset.formAction

      store.dispatch(openProductOptionsModal(name, options, images, price, code, formAction))
    })
  })

  document.querySelectorAll('[data-modal="details"]').forEach(el => {
    el.addEventListener('click', () => {

      const name       = el.dataset.productName
      const images     = JSON.parse(el.dataset.productImages)
      const price      = JSON.parse(el.dataset.productPrice)
      const formAction = el.dataset.formAction

      store.dispatch(openProductDetailsModal(name, images, price, formAction))
    })
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

init()
