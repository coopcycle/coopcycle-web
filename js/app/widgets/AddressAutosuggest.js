import React from 'react'
import { render } from 'react-dom'
import AddressAutosuggest from '../components/AddressAutosuggest'

const defaultOptions = {
  address: '',
  addresses: [],
  geohash: '',
  onAddressSelected: () => {},
  required: false,
  preciseOnly: false,
  reportValidity: false,
  inputName: undefined,
  inputId: undefined,
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
      onAddressSelected={ overrideOptions.onAddressSelected }
      required={ overrideOptions.required }
      preciseOnly={ overrideOptions.preciseOnly }
      reportValidity={ overrideOptions.reportValidity }
      inputName={ overrideOptions.inputName }
      inputId={ overrideOptions.inputId } />,
    el
  )
}
