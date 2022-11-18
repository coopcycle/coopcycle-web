import React from 'react'
import { render } from 'react-dom'

import store from './address-storage'
import AddressAutosuggest from '../components/AddressAutosuggest'

// We used to store a string in "search_address", but now, we want objects
// This function will cleanup legacy behavior
function resolveAddress(form) {

  const addressInput = form.querySelector('input[name="address"]')

  if (addressInput && addressInput.value) {
    return JSON.parse(atob(addressInput.value))
  }

}

window._paq = window._paq || []

document.querySelectorAll('[data-search="address"]').forEach((container) => {

  const el   = container.querySelector('[data-element]')
  const form = container.querySelector('[data-form]')

  if (el) {

    const addresses =
      container.dataset.addresses ? JSON.parse(container.dataset.addresses) : []

    const restaurants =
      container.dataset.restaurants ? JSON.parse(container.dataset.restaurants) : []

    render(
      <AddressAutosuggest
        address={ resolveAddress(form) }
        addresses={ addresses }
        restaurants={ restaurants }
        geohash={ store.get('search_geohash', '') }
        onAddressSelected={ (value, address) => {

          const addressInput = form.querySelector('input[name="address"]')
          const geohashInput = form.querySelector('input[name="geohash"]')

          if (address.geohash !== geohashInput.value) {

            const trackingCategory = container.dataset.trackingCategory
            if (trackingCategory) {
              window._paq.push(['trackEvent', trackingCategory, 'searchAddress', value])
            }

            geohashInput.value = address.geohash
            addressInput.value = btoa(JSON.stringify(address))

            form.submit()
          }

        }}
        required={ false }
        preciseOnly={ false }
        reportValidity={ false } />, el)
  }

})
