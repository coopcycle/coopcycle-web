import React, { StrictMode } from 'react'
import { createRoot } from 'react-dom/client';
import { createPortal } from 'react-dom'
import { Provider } from 'react-redux'
import { I18nextProvider } from 'react-i18next'
import Modal from 'react-modal'
import _ from 'lodash'

import i18n, { getCountry } from '../i18n'
import { createStoreFromPreloadedState } from './redux/store'
import { queueAddItem, openProductOptionsModal } from './redux/actions'
import storage from '../search/address-storage'
import { initLoopeatContext } from './loopeat'

require('gasparesganga-jquery-loading-overlay')

import './item.scss'
import './header.scss'
import './menu.scss'
import './components/Order/index.scss'

import ProductOptionsModal
  from './components/ProductDetails/ProductOptionsModal'
import RestaurantModal from './components/RestaurantModal'
import InvitePeopleToOrderModal from './components/InvitePeopleToOrderModal'
import SetGuestCustomerEmailModal from './components/SetGuestCustomerEmailModal'
import LoopeatModal from './components/LoopeatModal'
import FulfillmentDetails from './components/Order/FulfillmentDetails'
import { OrderOverlay, StickyOrder } from './components/Order'

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

  document.querySelectorAll('[data-modal="product-details"]').forEach(el => {
    el.addEventListener('click', () => {
      const product    = JSON.parse(el.dataset.product)
      const options    = JSON.parse(el.dataset.productOptions)
      const images     = JSON.parse(el.dataset.productImages)
      const price      = JSON.parse(el.dataset.productPrice)
      const formAction = el.dataset.formAction

      store.dispatch(openProductOptionsModal(product, options, images, price, formAction))
    })
  })

  const restaurantDataElement = document.querySelector('#js-restaurant-data')
  const addressesDataElement = document.querySelector('#js-addresses-data')
  const loopeatDataElement = document.querySelector('#js-loopeat')

  const restaurant = JSON.parse(restaurantDataElement.dataset.restaurant)
  const times = JSON.parse(restaurantDataElement.dataset.times)
  const isPlayer = JSON.parse(restaurantDataElement.dataset.isPlayer)
  const isGroupOrdersEnabled = JSON.parse(restaurantDataElement.dataset.isGroupOrdersEnabled)
  const addresses = JSON.parse(addressesDataElement.dataset.addresses)

  let cart = JSON.parse(restaurantDataElement.dataset.cart)

  initLoopeatContext(JSON.parse(loopeatDataElement.dataset.context))

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
    isPlayer,
    isGroupOrdersEnabled,
  }

  store = createStoreFromPreloadedState(state)

  Modal.setAppElement(container)

  // desktop only
  const fulfilmentDetailsContainer = document.getElementById('container__fulfilment-details')

  const root = createRoot(container);
  root.render(
    <StrictMode>
      <Provider store={ store }>
        <I18nextProvider i18n={ i18n }>
          {createPortal(<FulfillmentDetails />, fulfilmentDetailsContainer)}
          <StickyOrder />
          <OrderOverlay />
          <ProductOptionsModal />
          <RestaurantModal />
          <InvitePeopleToOrderModal />
          <SetGuestCustomerEmailModal />
          <LoopeatModal />
        </I18nextProvider>
      </Provider>
    </StrictMode>
  )

}

$('#menu').LoadingOverlay('show', {
  image: false,
})

init()
