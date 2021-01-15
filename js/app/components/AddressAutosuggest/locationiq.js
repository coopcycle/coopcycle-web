import React from 'react'
import axios from 'axios'

import LocationIQ from './locationiq.png'

const client = axios.create({
  baseURL: 'https://api.locationiq.com',
})

let accessToken = null
function getAccessToken() {
  if (null === accessToken) {
    const el = document.getElementById('locationiq')
    if (el) {
      accessToken = el.dataset.accessToken
    }
  }

  return accessToken
}

export const onSuggestionsFetchRequested = function({ value }) {

  client.get(`/v1/autocomplete.php?key=${getAccessToken()}&q=${encodeURIComponent(value.substring(0, 200))}&countrycodes=${this.country}&accept-language=${this.language}&dedupe=1&tag=place:house`)
    .then(response => {

      const predictionsAsSuggestions = response.data.map((result, idx) => ({
        type: 'prediction',
        value: result.display_name,
        id: result.place_id,
        description: result.display_name,
        index: idx,
        lat: parseFloat(result.lat),
        lng: parseFloat(result.lon),
        locationiq: result,
      }))

      this._autocompleteCallback(predictionsAsSuggestions, value)
    })
}

export function poweredBy() {

  return (
    <img width="76.5" height="16" src={ LocationIQ } />
  )
}

const locationiqToAddress = (locationiq) => ({
  // FIXME Use "geo" key everywhere, and remove
  latitude: parseFloat(locationiq.lat),
  longitude: parseFloat(locationiq.lon),
  geo: {
    latitude: parseFloat(locationiq.lat),
    longitude: parseFloat(locationiq.lon),
  },
  addressCountry: (locationiq.address && locationiq.address.country) || '',
  addressLocality: (locationiq.address && locationiq.address.city) || '',
  addressRegion: (locationiq.address && locationiq.address.state) || '',
  postalCode: (locationiq.address && locationiq.address.postcode) || '',
  streetAddress: locationiq.display_name,
  isPrecise: true,
  needsGeocoding: false,
})

export const transformSuggestion = function (suggestion) {

  const locationiq = suggestion.locationiq

  return locationiqToAddress(locationiq)
}

export const geocode = function (text, country = 'en', language = 'en') {

  return new Promise((resolve) => {
    client.get(`/v1/autocomplete.php?key=${getAccessToken()}&q=${encodeURIComponent(text.substring(0, 200))}&countrycodes=${country}&accept-language=${language}&dedupe=1&tag=place:house&limit=1`)
      .then(response => {
        if (response.data.length > 0) {
          resolve(locationiqToAddress(response.data[0]))
        } else {
          resolve(null)
        }
      })
      .catch(() => {
        resolve(null)
      })
  })
}
