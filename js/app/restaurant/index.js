import React from 'react'
import { render, unmountComponentAtNode } from 'react-dom'
import { Provider } from 'react-redux'
import { I18nextProvider } from 'react-i18next'
import Modal from 'react-modal'
import _ from 'lodash'

import i18n, { getCountry } from '../i18n'
import { createStoreFromPreloadedState } from '../cart/redux/store'
import { addItem, addItemWithOptions, queueAddItem } from '../cart/redux/actions'
import Cart from '../cart/components/Cart'
import ProductOptionsModal from './components/ProductOptionsModal'
import ProductImagesCarousel from './components/ProductImagesCarousel'
import { ProductOptionsModalProvider } from './components/ProductOptionsModalContext'
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

  // We update the base price on "show.bs.modal" to avoid flickering
  $('#product-options').on('show.bs.modal', function(event) {

    const $modal = $(this)
    $modal.find('.modal-title').text(event.relatedTarget.dataset.productName)

    const productOptions =
      JSON.parse(event.relatedTarget.dataset.productOptions)
    const productImages =
      JSON.parse(event.relatedTarget.dataset.productImages)

    render(
      <ProductOptionsModalProvider
        options={ productOptions }
        price={ JSON.parse(event.relatedTarget.dataset.productPrice) }>
        <ProductOptionsModal
          code={ event.relatedTarget.dataset.productCode }
          options={ productOptions }
          formAction={ event.relatedTarget.dataset.formAction }
          images={ productImages }
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

          } } />
      </ProductOptionsModalProvider>,
      this.querySelector('.modal-body [data-options-container]')
    )
  })

  $('#product-options').on('shown.bs.modal', function() {
    window._paq.push(['trackEvent', 'Checkout', 'showOptions'])
  })

  $('#product-options').on('hidden.bs.modal', function() {
    unmountComponentAtNode(this.querySelector('.modal-body [data-options-container]'))
    window._paq.push(['trackEvent', 'Checkout', 'hideOptions'])
  })

  // ---

  $('#product-details').on('show.bs.modal', function(event) {

    const images = JSON.parse(event.relatedTarget.dataset.productImages)
    const productPrice = JSON.parse(event.relatedTarget.dataset.productPrice)

    const $modal = $(this)
    $modal.find('.modal-title').text(event.relatedTarget.dataset.productName)
    $modal.find('form').attr('action', event.relatedTarget.dataset.formAction)
    $modal.find('button[type="submit"]').text((productPrice / 100).formatMoney())

    render(
      <ProductImagesCarousel images={ images } />,
      this.querySelector('.modal-body [data-carousel-container]')
    )
  })

  $('#product-details').on('hidden.bs.modal', function() {
    unmountComponentAtNode(this.querySelector('.modal-body [data-carousel-container]'))
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
