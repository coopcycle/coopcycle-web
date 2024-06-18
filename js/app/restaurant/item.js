import React from 'react'
import { createRoot } from 'react-dom/client';
import { createPortal } from 'react-dom'
import { Provider } from 'react-redux'
import { I18nextProvider } from 'react-i18next'
import Modal from 'react-modal'
import _ from 'lodash'

import i18n, { getCountry } from '../i18n'
import { createStoreFromPreloadedState } from './redux/store'
import {
  queueAddItem,
  openProductOptionsModal,
  fetchRequest,
  fetchFailure,
} from './redux/actions'
import storage from '../search/address-storage'
import { initLoopeatContext } from './loopeat'

require('gasparesganga-jquery-loading-overlay')

import './item.scss'
import './header.scss'
import './menu.scss'
import './components/Order/index.scss'
import '../components/order/index.scss'

import ProductOptionsModal
  from './components/ProductDetails/ProductOptionsModal'
import ChangeRestaurantOnAddProductModal from './components/ChangeRestaurantOnAddProductModal'
import InvitePeopleToOrderModal from './components/InvitePeopleToOrderModal'
import SetGuestCustomerEmailModal from './components/SetGuestCustomerEmailModal'
import LoopeatModal from './components/LoopeatModal'
import FulfillmentDetails from './components/Order/FulfillmentDetails'
import { OrderOverlay, StickyOrder } from './components/Order'
import {
  selectCanAddToExistingCart,
  selectCartShippingTimeRange,
  selectCartTiming,
} from './redux/selectors'
import {
  checkTimeRange,
} from '../utils/order/helpers'
import {
  timeRangeSlice,
} from '../components/order/timeRange/reduxSlice'
import {
  accountSlice,
} from '../entities/account/reduxSlice'
import { buildGuestInitialState } from '../entities/guest/utils'
import { guestSlice } from '../entities/guest/reduxSlice'
import { orderSlice } from '../entities/order/reduxSlice'

window._paq = window._paq || []

let store

function setMenuLoading(isLoading) {
  if (isLoading) {
    $('#menu').LoadingOverlay('show', {
      image: false,
    })
  } else {
    $('#menu').LoadingOverlay('hide', {
      image: false,
    })
  }
}

function init() {

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

  const cartForm = document.querySelector('form[name="cart"]')

  cartForm.addEventListener('submit', async function(event) {
    event.preventDefault()

    setMenuLoading(true)
    store.dispatch(fetchRequest()) // will trigger loading state in some react components

    const canAddToExistingCart = selectCanAddToExistingCart(store.getState())

    if (canAddToExistingCart) {
      const shippingTimeRange = selectCartShippingTimeRange(store.getState())
      // if the customer has already selected the time range, it will be checked on the server side
      if (!shippingTimeRange) {
        const displayedTiming = selectCartTiming(store.getState())

        try {
          await checkTimeRange(displayedTiming?.range, store.getState, store.dispatch)
        } catch (error) {
          setMenuLoading(false)
          store.dispatch(fetchFailure()) // only to hide loading state in some react components
          return
        }
      }
    }

    cartForm.submit()
  })

  const restaurantDataElement = document.querySelector('#js-restaurant-data')
  const addressesDataElement = document.querySelector('#js-addresses-data')
  const loopeatDataElement = document.querySelector('#js-loopeat')

  const restaurant = JSON.parse(restaurantDataElement.dataset.restaurant)
  const restaurantTiming = JSON.parse(restaurantDataElement.dataset.restaurantTiming)
  const cartTiming = JSON.parse(restaurantDataElement.dataset.cartTiming)
  const orderAccessToken = restaurantDataElement.dataset.orderAccessToken || null
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
    [accountSlice.name]: accountSlice.getInitialState(),
    [guestSlice.name]: buildGuestInitialState(cart['@id'], orderAccessToken),
    cart,
    [orderSlice.name]: {
      ...orderSlice.getInitialState(),
      '@id': cart['@id'],
      shippingTimeRange: cart.shippingTimeRange,
    },
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
    restaurantTiming,
    cartTiming,
    country: getCountry(),
    isPlayer,
    isGroupOrdersEnabled,
    [timeRangeSlice.name]: timeRangeSlice.getInitialState(),
  }

  store = createStoreFromPreloadedState(state)

  Modal.setAppElement(container)

  // desktop only
  const fulfilmentDetailsContainer = document.getElementById('restaurant__fulfilment-details__container')

  const root = createRoot(container);
  root.render(
      <Provider store={ store }>
        <I18nextProvider i18n={ i18n }>
          {createPortal(<FulfillmentDetails />, fulfilmentDetailsContainer)}
          <StickyOrder />
          <OrderOverlay />
          <ProductOptionsModal />
          <ChangeRestaurantOnAddProductModal />
          <InvitePeopleToOrderModal />
          <SetGuestCustomerEmailModal />
          <LoopeatModal />
        </I18nextProvider>
      </Provider>
  )
}

setMenuLoading(true)
init()
