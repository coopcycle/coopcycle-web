import React from 'react'
import _ from 'lodash'
import ngeohash from 'ngeohash'

import PoweredByGoogle from './powered_by_google_on_white_hdpi.png'

const placeToAddress = (place, value) => {

  const addressDict = {}

  place.address_components.forEach(function (item) {
    addressDict[item.types[0]] = item.long_name
  })

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
    // We *DO NOT* use place.formatted_address, because it's different
    // from what has been entered & is visible in the search field
    // Also, some hacky code (js/app/utils/address.js) relies on the fact that what is entered
    // corresponds to the "streetAddress" property
    streetAddress: value, // place.formatted_address,
    // street_address indicates a precise street address
    // premise indicates a named location, usually a building or collection of buildings with a common name
    // subpremise indicates a first-order entity below a named location, usually a singular building within a collection of buildings with a common name
    // WARNING
    // we may have "subpremise" when the user enters the floor after the address,
    // for ex: Calle Gorbea, 46, 6d
    isPrecise: _.includes(place.types, 'street_address') || _.includes(place.types, 'premise') || _.includes(place.types, 'subpremise'),
    needsGeocoding: false,
  }
}

let location
let autocompleteService
let geocoderService
let latLngBounds

const autocompleteOptions = {
  types: ['address'],
}

export const onSuggestionsFetchRequested = function({ value }) {

  // https://developers.google.com/maps/documentation/javascript/reference/places-autocomplete-service
  autocompleteService.getPlacePredictions({
    ...autocompleteOptions,
    // https://developers.google.com/maps/documentation/javascript/reference/places-autocomplete-service?hl=en#AutocompletionRequest.locationRestriction
    locationRestriction: latLngBounds,
    input: value,
  }, (predictions, status) => {

    if (status === window.google.maps.places.PlacesServiceStatus.OK && Array.isArray(predictions)) {

      const predictionsAsSuggestions = predictions.map((result, idx) => ({
        type: 'prediction',
        value: result.description,
        id: result.place_id,
        description: result.description,
        index: idx,
        google: result,
        // *WARNING*
        // At this step, we DON'T have the lat/lng
        // It will be obtained when selecting the suggestion
      }))

      this._autocompleteCallback(predictionsAsSuggestions, value)

    }

  })

}

export function onSuggestionSelected(event, { suggestion }) {

  // TODO Remove code duplication

  if (suggestion.type === 'restaurant') {
    window.location.href = window.Routing.generate('restaurant', {
      id: suggestion.restaurant.id
    })
  }

  if (suggestion.type === 'address') {

    const geohash = ngeohash.encode(
      suggestion.address.geo.latitude,
      suggestion.address.geo.longitude,
      11
    )

    const address = {
      ...suggestion.address,
      geohash,
    }

    this.props.onAddressSelected(suggestion.value, address, suggestion.type)

    return
  }

  geocoderService.geocode({ placeId: suggestion.google.place_id }, (results, status) => {
    if (status === window.google.maps.GeocoderStatus.OK && results.length === 1) {

      const place = results[0]
      const lat = place.geometry.location.lat()
      const lng = place.geometry.location.lng()

      const geohash = ngeohash.encode(lat, lng, 11)

      const address = {
        ...placeToAddress(place, this.state.value),
        geohash,
      }

      this.props.onAddressSelected(this.state.value, address, suggestion.type)
    }
  })
}

export function poweredBy() {

  return (
    <img width="128" height="16" src={ PoweredByGoogle } />
  )
}

export const transformSuggestion = function () {

  // TODO Implement
}

export const geocode = function (text) {

  // https://developers.google.com/maps/documentation/javascript/geocoding
  return new Promise((resolve) => {
    geocoderService.geocode({ address: text }, (results, status) => {
      if (status === window.google.maps.GeocoderStatus.OK && results.length > 0) {
        const place = results[0]
        resolve(placeToAddress(place, text))
      } else {
        resolve(null)
      }
    })
  })
}

export const configure = function (options) {

  if (!autocompleteService && !geocoderService && !location) {

    autocompleteService = new window.google.maps.places.AutocompleteService()
    geocoderService     = new window.google.maps.Geocoder()

    const [ lat, lng ] = options.location.split(',').map(parseFloat)
    const [ swLat, swLng, neLat, neLng ] = options.latLngBounds.split(',').map(coord => parseFloat(coord))

    // https://developers.google.com/maps/documentation/javascript/reference/coordinates?hl=en#LatLngBounds
    latLngBounds = new window.google.maps.LatLngBounds(
      new window.google.maps.LatLng(swLat, swLng),
      new window.google.maps.LatLng(neLat, neLng)
    )

    location = new window.google.maps.LatLng(lat, lng)
  }

}
