import React from 'react'
import { render } from 'react-dom'

import store from './address-storage'
import AddressAutosuggest from '../components/AddressAutosuggest'

// We used to store a string in "search_address", but now, we want objects
// This function will cleanup legacy behavior
function resolveAddress(form) {

  const addressInput = form.querySelector('input[name="address"]')

  if (addressInput && addressInput.value) {
    return JSON.parse(decodeURIComponent(atob(addressInput.value)))
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
        onClear={ () => {
          // clear geohash and address query params but keep others (filters)
          const addressInput = form.querySelector('input[name="address"]')
          const geohashInput = form.querySelector('input[name="geohash"]')

          addressInput.parentNode.removeChild(addressInput)
          geohashInput.parentNode.removeChild(geohashInput)

          const searchParams = new URLSearchParams(window.location.search);
          searchParams.delete('geohash')
          searchParams.delete('address')

          for (const [key, value] of searchParams.entries()) {
            const newInput = document.createElement('input')
            newInput.setAttribute('type', 'hidden')
            newInput.setAttribute('name', key)
            newInput.value = value
            form.appendChild(newInput)
          }

          form.submit()
        }}
        onAddressSelected={ (value, address) => {

          const addressInput = form.querySelector('input[name="address"]')
          const geohashInput = form.querySelector('input[name="geohash"]')

          if (address.geohash !== geohashInput.value) {

            const trackingCategory = container.dataset.trackingCategory
            if (trackingCategory) {
              window._paq.push(['trackEvent', trackingCategory, 'searchAddress', value])
            }

            geohashInput.value = address.geohash
            addressInput.value = btoa(encodeURIComponent(JSON.stringify(address)))

            const searchParams = new URLSearchParams(window.location.search);

            // submit form including existing filters applied
            for (const [key, value] of searchParams.entries()) {
              if (key !== 'geohash' && key !== 'address') {
                const newInput = document.createElement('input')
                newInput.setAttribute('type', 'hidden')
                newInput.setAttribute('name', key)
                newInput.value = value
                form.appendChild(newInput)
              }
            }

            form.submit()
          }

        }}
        required={ false }
        preciseOnly={ false }
        reportValidity={ false } />, el)
  }

})
