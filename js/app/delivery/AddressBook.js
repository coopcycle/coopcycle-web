import React from 'react'
import { render } from 'react-dom'

import AddressAutosuggest from '../components/AddressAutosuggest'

export default function(el, options) {

  const {
    existingAddressControl,
    newAddressControl,
    isNewAddressControl
  } = options

  const addresses = []
  Array.from(existingAddressControl.options).forEach(option => {
    if (option.dataset.address) {
      addresses.push(JSON.parse(option.dataset.address))
    }
  })

  let autosuggestProps = {}

  // Replace the existing address dropdown by a hidden input with the same name & value
  const existingAddressControlHidden = document.createElement('input')

  const existingAddressControlName = existingAddressControl.name
  const existingAddressControlValue = existingAddressControl.value
  const existingAddressControlSelected = existingAddressControl.options[existingAddressControl.selectedIndex]

  existingAddressControlHidden.setAttribute('type', 'hidden')
  existingAddressControlHidden.setAttribute('name', existingAddressControlName)
  existingAddressControlHidden.setAttribute('value', existingAddressControlValue)

  existingAddressControl.remove()
  el.appendChild(existingAddressControlHidden)

  // Replace the new address text field by a hidden input with the same name & value
  const newAddressControlHidden = document.createElement('input')

  const newAddressControlName = newAddressControl.name
  const newAddressControlValue = newAddressControl.value
  const newAddressControlId = newAddressControl.id

  if (newAddressControl.hasAttribute('placeholder')) {
    autosuggestProps = {
      ...autosuggestProps,
      placeholder: newAddressControl.getAttribute('placeholder')
    }
  }

  newAddressControlHidden.setAttribute('type', 'hidden')
  newAddressControlHidden.setAttribute('name', newAddressControlName)
  newAddressControlHidden.setAttribute('value', newAddressControlValue)
  newAddressControlHidden.setAttribute('id', newAddressControlId)

  newAddressControl.remove()
  el.appendChild(newAddressControlHidden)

  // Replace the new address checkbox by a hidden input with the same name & value
  const isNewAddressControlHidden = document.createElement('input')

  const isNewAddressControlName = isNewAddressControl.name
  const isNewAddressControlValue = isNewAddressControl.value
  const isNewAddressControlId = isNewAddressControl.id

  isNewAddressControlHidden.setAttribute('type', 'hidden')
  isNewAddressControlHidden.setAttribute('name', isNewAddressControlName)
  isNewAddressControlHidden.setAttribute('value', isNewAddressControlValue)
  isNewAddressControlHidden.setAttribute('id', isNewAddressControlId)

  isNewAddressControl.closest('.checkbox').remove()
  if (isNewAddressControl.checked) {
    el.appendChild(isNewAddressControlHidden)
  }

  // Callback with initial data
  let address

  if (existingAddressControlSelected.dataset.address) {
    address = JSON.parse(existingAddressControlSelected.dataset.address)
    if (options.onReady && typeof options.onReady === 'function') {
      options.onReady(address)
    }
  }

  if (isNewAddressControl.checked && newAddressControl.value) {
    address = {
      streetAddress: newAddressControl.value,
      postalCode: el.querySelector('[data-address-prop="postalCode"]').value,
      addressLocality: el.querySelector('[data-address-prop="addressLocality"]').value,
      latitude: el.querySelector('[data-address-prop="latitude"]').value,
      longitude: el.querySelector('[data-address-prop="longitude"]').value,
      geo: {
        latitude: el.querySelector('[data-address-prop="latitude"]').value,
        longitude: el.querySelector('[data-address-prop="longitude"]').value,
      }
    }
    if (options.onReady && typeof options.onReady === 'function') {
      options.onReady(address)
    }
  }

  const reactContainer = document.createElement('div')

  el.prepend(reactContainer)

  render(
    <AddressAutosuggest
      addresses={ addresses }
      address={ address }
      geohash={ '' }
      required={ true }
      reportValidity={ true }
      preciseOnly={ true }
      onAddressSelected={ (value, address) => {

        if (address['@id']) {
          existingAddressControlHidden.value = address['@id']
          isNewAddressControlHidden.remove()
        } else {
          newAddressControlHidden.value = address.streetAddress
          el.querySelector('[data-address-prop="postalCode"]').value = address.postalCode
          el.querySelector('[data-address-prop="addressLocality"]').value = address.addressLocality
          el.querySelector('[data-address-prop="latitude"]').value = address.latitude
          el.querySelector('[data-address-prop="longitude"]').value = address.longitude

          if (!document.documentElement.contains(isNewAddressControlHidden)) {
            el.appendChild(isNewAddressControlHidden)
          }
        }

        if (options.onChange && typeof options.onChange === 'function') {
          options.onChange(address)
        }

      } }
      { ...autosuggestProps } />,
    reactContainer
  )
}
