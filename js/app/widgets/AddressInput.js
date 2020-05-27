import _ from 'lodash'
import { getCountry } from '../i18n'

const mapsOptions = {
  types: ['address'],
  componentRestrictions: {
    country: (getCountry() || 'fr').toUpperCase()
  }
}

const addressTypeToPropertyName = {
  postal_code: 'postalCode',
  locality: 'addressLocality'
}

export default function(el, options) {

  options = options || {
    elements: {}
  }

  if (!window.google) {
    // eslint-disable-next-line no-console
    console.error('Google Maps not loaded')
    return
  }

  google.maps.event.clearListeners(el, 'place_changed')
  const autocomplete = new google.maps.places.Autocomplete(el, mapsOptions)

  if (Object.prototype.hasOwnProperty.call(options, 'onLoad') && typeof options.onLoad === 'function') {
    options.onLoad()
  }

  autocomplete.addListener('place_changed', function() {

    const place = autocomplete.getPlace()

    if (!place.geometry) {
      return
    }

    if (Object.prototype.hasOwnProperty.call(options.elements, 'latitude')) {
      options.elements.latitude.value = place.geometry.location.lat()
    }
    if (Object.prototype.hasOwnProperty.call(options.elements, 'longitude')) {
      options.elements.longitude.value = place.geometry.location.lng()
    }

    if (Object.prototype.hasOwnProperty.call(options, 'onLocationChange') && typeof options.onLocationChange === 'function') {
      options.onLocationChange({
        latitude: place.geometry.location.lat(),
        longitude: place.geometry.location.lng()
      })
    }

    const propertyNames = {}
    for (var i = 0; i < place.address_components.length; i++) {
      var addressType = place.address_components[i].types[0]
      if (Object.prototype.hasOwnProperty.call(addressTypeToPropertyName, addressType)) {
        propertyNames[addressTypeToPropertyName[addressType]] = place.address_components[i].long_name
      }
    }

    if (Object.prototype.hasOwnProperty.call(options, 'onAddressChange') && typeof options.onAddressChange === 'function') {
      options.onAddressChange({ streetAddress: el.value, ...propertyNames })
    }

    _.each(propertyNames, (value, name) => {
      if (Object.prototype.hasOwnProperty.call(options.elements, name)) {
        options.elements[name].value = value
      }
    })

  })
}
