import React from 'react'
import _ from 'lodash'
import ngeohash from 'ngeohash'

import PoweredByGoogle from './powered_by_google_on_white_hdpi.png'

const placeToAddress = (place) => {

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
    streetAddress: place.formatted_address,
    // street_address indicates a precise street address
    isPrecise: _.includes(place.types, 'street_address') || _.includes(place.types, 'premise'),
    needsGeocoding: false,
  }
}

let location
let autocompleteService
let geocoderService

const autocompleteOptions = {
  types: ['address'],
  radius: 50000,
}

export const onSuggestionsFetchRequested = function({ value }) {

  // https://developers.google.com/maps/documentation/javascript/reference/places-autocomplete-service
  autocompleteService.getPlacePredictions({
    ...autocompleteOptions,
    location: location,
    input: value,
  }, (predictions, status) => {

    if (status === window.google.maps.places.PlacesServiceStatus.OK && Array.isArray(predictions)) {

      const predictionsAsSuggestions = predictions.map((result, idx) => ({
        type: 'prediction',
        value: result.description,
        id: result.place_id,
        description: result.description,
        index: idx,
        // lat: parseFloat(result.lat),
        // lng: parseFloat(result.lon),
        google: result,
      }))

      this._autocompleteCallback(predictionsAsSuggestions, value)

    }

  })

}

export function onSuggestionSelected(event, { suggestion }) {

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

export const geocode = function () {

  // TODO Implement
}

export const configure = function (options) {

  // FIXME Only execute once

  window.initMap = function() {
    autocompleteService = new window.google.maps.places.AutocompleteService()
    geocoderService     = new window.google.maps.Geocoder()

    const [ lat, lng ] = options.location.split(',').map(parseFloat)

    location = new window.google.maps.LatLng(lat, lng)
  }

}
