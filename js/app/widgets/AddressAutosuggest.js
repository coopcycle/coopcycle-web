import React from 'react'
import { render } from 'react-dom'
import AddressAutosuggest from '../components/AddressAutosuggest'

export default function(el, options) {

  render(
    <AddressAutosuggest
      address={ options.address }
      geohash={ options.geohash }
      onAddressSelected={ options.onAddressSelected } />,
    el
  )
}
