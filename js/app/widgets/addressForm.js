const addressElements = {
  latitude: '$1ddress_latitude',
  longitude: '$1ddress_longitude',
  postalCode: '$1ddress_postalCode',
  addressLocality: '$1ddress_addressLocality',
}

const getValue = (address, prop) => {
  if (prop === 'latitude' || prop === 'longitude' && address.geo) {

    return address.geo[prop]
  }

  return address[prop]
}

export const addressMapper = (el, address) => {

  for (const addressProp in addressElements) {
    const addressEl = document.getElementById(
      el.getAttribute('id').replace(/([aA])ddress_streetAddress/, addressElements[addressProp])
    )

    if (addressEl) {
      addressEl.value = getValue(address, addressProp)
    }
  }
}
