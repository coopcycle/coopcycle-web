import React from 'react'
import { render } from 'react-dom'
import AddressPicker from '../components/AddressPicker'

export default function(el, options) {

  options = {
    address: '',
    geohash: '',
    onChange: () => {},
    ...options
  }

  render(
    <AddressPicker
      address={ options.address }
      geohash={ options.geohash }
      onPlaceChange={ options.onChange } />, el)
}
