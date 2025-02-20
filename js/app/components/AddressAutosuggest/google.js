import React from 'react'
import _ from 'lodash'
import ngeohash from 'ngeohash'
import { v4 as uuidv4 } from 'uuid';

import PoweredByGoogle from './powered_by_google_on_white_hdpi.png'
import axios from 'axios'
import { localeDetector } from '../../i18n';

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

let isNewPlacesApi = true // indicates if "Places (new)" is set up
let googleApiKey
let sessionToken
let autocompleteService
let geocoderService
let latLngBounds

const autocompleteOptions = {
  types: ['address'],
}

export const onSuggestionsFetchRequested = function({ value }) {
  
  if (isNewPlacesApi) {
    if (!sessionToken) {
      sessionToken = uuidv4()
    }
    
    axios.post(
      'https://places.googleapis.com/v1/places:autocomplete',
      {
        input: value,
        sessionToken: sessionToken,
        locationRestriction: { "rectangle": {
          "low" : {
            "latitude": latLngBounds.getSouthWest().lat(),
            "longitude": latLngBounds.getSouthWest().lng()
          },
          "high" : {
            "latitude": latLngBounds.getNorthEast().lat(),
            "longitude": latLngBounds.getNorthEast().lng()
          },
        }},
        includedPrimaryTypes: ['street_address'],
        languageCode: localeDetector()
      },
      {headers: {"X-Goog-Api-Key": googleApiKey}}
    ).then(async resp => {
      const { suggestions } = resp.data
      let predictionsAsSuggestions

      // if no suggestions are found, the API returns nothing
      if (!suggestions) {
        predictionsAsSuggestions = []
      } else {
        predictionsAsSuggestions = suggestions.map((suggestion, idx) => {
          let prediction = suggestion.placePrediction
          return {
            type: 'prediction',
            value: prediction.text.text,
            id: prediction.placeId,
            description: prediction.text.text,
            index: idx,
            google: prediction,
            // *WARNING*
            // At this step, we DON'T have the lat/lng
            // It will be obtained when selecting the suggestion
          }
        })
      }
      this._autocompleteCallback(predictionsAsSuggestions, value)

    }).catch(error => {
      // our API key is not valid for the places API (new), fallback to legacy API
      if (error.response.status === 403) {
        console.error("[Google adapter] Using legacy Places API")
        isNewPlacesApi = false
        // autocompletion service for legacy places API
        autocompleteService = new window.google.maps.places.AutocompleteService()
        this.onSuggestionsFetchRequested({ value })
      }
    })
  } else {
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

  sessionToken = null

  const placeId = isNewPlacesApi ? suggestion.google.placeId : suggestion.google.place_id

  geocoderService.geocode({ placeId: placeId }, (results, status) => {
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
    } else {
      console.error("[Google adapter] placeId was not geocoded on address selection")
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

  if (autocompleteService || geocoderService) {
    return
  }

  const [ swLat, swLng, neLat, neLng ] = options.latLngBounds.split(',').map(coord => parseFloat(coord))
  
  geocoderService = new window.google.maps.Geocoder()

  googleApiKey = document.getElementById('google').dataset.apiKey

  // https://developers.google.com/maps/documentation/javascript/reference/coordinates?hl=en#LatLngBounds
  latLngBounds = new window.google.maps.LatLngBounds(
    new window.google.maps.LatLng(swLat, swLng),
    new window.google.maps.LatLng(neLat, neLng)
  )
}
