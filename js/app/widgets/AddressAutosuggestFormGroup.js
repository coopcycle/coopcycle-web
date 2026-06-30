import AddressAutosuggest from './AddressAutosuggest'

const addressElements = {
  latitude: '$1ddress_latitude',
  longitude: '$1ddress_longitude',
  postalCode: '$1ddress_postalCode',
  addressLocality: '$1ddress_addressLocality',
}

export default function (el) {

  // Try to build an address object
  let address = {
    streetAddress: el.value
  }
  for (const addressProp in addressElements) {
    const addressEl = document.getElementById(
      el.getAttribute('id').replace(/([aA])ddress_streetAddress/, addressElements[addressProp])
    )
    if (addressEl) {
      address = {
        ...address,
        [addressProp]: addressEl.value
      }
    }
  }

  address = {
    ...address,
    geo: {
      latitude: address.latitude,
      longitude: address.longitude,
    }
  }

  new AddressAutosuggest(
    el.closest('.form-group') || el.parentElement,
    {
      required: el.required,
      address,
      inputProps: {
        id: el.getAttribute('id'),
        name: el.getAttribute('name'),
      },
      onAddressSelected: (text, address) => {
        for (const addressProp in addressElements) {
          const addressEl = document.getElementById(
            el.getAttribute('id').replace(/([aA])ddress_streetAddress/, addressElements[addressProp])
          )
          if (addressEl) {
            addressEl.value = address[addressProp]
          }
        }
      },
      disabled: el.disabled,
      placeholder: el.placeholder,
    }
  )

}
