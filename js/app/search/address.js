import React from 'react'
import { render } from 'react-dom'

import engine  from 'store/src/store-engine'
import session from 'store/storages/sessionStorage'
import cookie  from 'store/storages/cookieStorage'

const store = engine.createStore([ session, cookie ])

import AddressAutosuggest from '../components/AddressAutosuggest'

window._paq = window._paq || []

window.initMap = function() {

  document.querySelectorAll('[data-search="address"]').forEach((container) => {

    const el   = container.querySelector('[data-element]')
    const form = container.querySelector('[data-form]')

    if (el) {

      const addresses =
        container.dataset.addresses ? JSON.parse(container.dataset.addresses) : []

      render(
        <AddressAutosuggest
          address={ store.get('search_address', '') }
          addresses={ addresses }
          geohash={ store.get('search_geohash', '') }
          onAddressSelected={ (value, address, type) => {

            const addressInput = form.querySelector('input[name="address"]')
            const geohashInput = form.querySelector('input[name="geohash"]')

            if (address.geohash !== geohashInput.value) {

              if (type === 'address') {
                if (!addressInput) {
                  const newAddressInput = document.createElement('input')
                  newAddressInput.setAttribute('type', 'hidden')
                  newAddressInput.setAttribute('name', 'address')
                  newAddressInput.value = btoa(address['@id'])
                  form.appendChild(newAddressInput)
                }
              }

              if (type === 'prediction') {
                if (addressInput) {
                  addressInput.parentNode.removeChild(addressInput)
                }
              }

              store.set('search_geohash', address.geohash)
              store.set('search_address', value)

              const trackingCategory = container.dataset.trackingCategory
              if (trackingCategory) {
                window._paq.push(['trackEvent', 'Homepage', 'searchAddress', value])
              }

              geohashInput.value = address.geohash

              form.submit()

            }

          }}
          required={ false }
          preciseOnly={ false }
          reportValidity={ false } />, el)
    }

  })
}
