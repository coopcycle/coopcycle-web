import _ from 'lodash'

export const placeToAddress = (place, value = '') => {

  const addressDict = {}

  place.address_components.forEach(function (item) {
    addressDict[item.types[0]] = item.long_name
  })

  if (value) {
    addressDict.streetAddress = value
  } else {
    addressDict.streetAddress = addressDict.street_number ?
      `${addressDict.street_number} ${addressDict.route}` : addressDict.route
  }

  return {
    // FIXME Use "geo" key everywhere, and remove
    latitude: place.geometry.location.lat(),
    longitude: place.geometry.location.lng(),
    geo: {
      latitude: place.geometry.location.lat(),
      longitude: place.geometry.location.lng(),
    },
    addressCountry: addressDict.country || '',
    addressLocality: addressDict.locality || '',
    addressRegion: addressDict.administrative_area_level_1 || '',
    postalCode: addressDict.postal_code || '',
    streetAddress: addressDict.streetAddress || '',
    // street_address indicates a precise street address
    isPrecise: _.includes(place.types, 'street_address') || _.includes(place.types, 'premise')
  }
}
