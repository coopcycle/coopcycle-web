import React from 'react'
import { render } from 'react-dom'
import AddressAutosuggest from '../components/AddressAutosuggest'

const defaultOptions = {
  address: '',
  addresses: [],
  geohash: '',
  onAddressSelected: (value, address) => {},
}

export default function(el, options) {

  const overrideOptions = {
    ...defaultOptions,
    ...options
  }

  render(
    <AddressAutosuggest
      address={ overrideOptions.address }
      addresses={ overrideOptions.addresses }
      geohash={ overrideOptions.geohash }
      onAddressSelected={ overrideOptions.onAddressSelected } />,
    el
  )
}
