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

let autocompleteService
let geocoderService
let latLngBounds
let sessionToken


export const onSuggestionsFetchRequested = function({ value }) {

  // https://developers.google.com/maps/documentation/javascript/reference/places-autocomplete-service
  autocompleteService.fetchAutocompleteSuggestions({
    sessionToken: sessionToken,
    // https://developers.google.com/maps/documentation/javascript/reference/places-autocomplete-service?hl=en#AutocompletionRequest.locationRestriction
    locationRestriction: latLngBounds,
    includedPrimaryTypes: ['street_address'],
    input: value,
  }).then(({suggestions}) => {

    Promise.all(
      suggestions.map(suggestion => {
        console.log(suggestion.placePrediction)
        console.log(suggestion.placePrediction.description)
        return suggestion.placePrediction.toPlace().fetchFields({fields: ["formattedAddress"]})})
    )
      .then(places => {
        const placesAsSuggestions = places.map((result, idx) => ({
          type: 'prediction',
          value: result.place.formattedAddress,
          id: result.place.id,
          description: result.place.formattedAddress,
          index: idx,
          google: result.place,
          // *WARNING*
          // At this step, we DON'T have the lat/lng
          // It will be obtained when selecting the suggestion
        }))

        this._autocompleteCallback(placesAsSuggestions, value)
      })
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

  geocoderService.geocode({ placeId: suggestion.google.id }, (results, status) => {
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


export const configure = async function (options) {

  if (!autocompleteService && !geocoderService) {

    const [coreLib, placesLib, geocodingLib] = await Promise.all([
      google.maps.importLibrary("core"),
      google.maps.importLibrary("places"),
      google.maps.importLibrary("geocoding")
    ])

    autocompleteService = placesLib.AutocompleteSuggestion
    geocoderService = new geocodingLib.Geocoder();

    sessionToken = new google.maps.places.AutocompleteSessionToken();

    const [ swLat, swLng, neLat, neLng ] = options.latLngBounds.split(',').map(coord => parseFloat(coord))

    // https://developers-dot-devsite-v2-prod.appspot.com/maps/documentation/javascript/reference/coordinates?hl=fr#LatLngBounds
    latLngBounds = new coreLib.LatLngBounds(
      new coreLib.LatLng(swLat, swLng),
      new coreLib.LatLng(neLat, neLng)
    )
  }
}
