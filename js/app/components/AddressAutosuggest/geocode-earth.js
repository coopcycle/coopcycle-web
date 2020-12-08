import React from 'react'
import axios from 'axios'

// https://github.com/pelias/documentation/blob/master/autocomplete.md

import GeocodeEarth from './geocode-earth.png'

const client = axios.create({
  baseURL: 'https://api.geocode.earth',
})

let apiKey = null
function getApiKey() {
  if (null === apiKey) {
    const el = document.getElementById('geocode-earth')
    if (el) {
      apiKey = el.dataset.apiKey
    }
  }

  return apiKey
}


let boundaryCircleLatlon = null
function getBoundaryCircleLatlon() {
  if (null === boundaryCircleLatlon) {
    const el = document.getElementById('geocode-earth')
    if (el) {
      boundaryCircleLatlon = el.dataset.boundaryCircleLatlon
    }
  }

  return boundaryCircleLatlon
}

export const onSuggestionsFetchRequested = function({ value }) {

  const latlon = getBoundaryCircleLatlon()
  const [ lat, lon ] = latlon.split(',')

  client.get(`/v1/autocomplete?text=${encodeURIComponent(value)}&size=5&api_key=${getApiKey()}&boundary.circle.lat=${lat}&boundary.circle.lon=${lon}&lang=${this.language}`)
    .then(response => {

      const predictionsAsSuggestions = response.data.features.map((feature, idx) => ({
        type: 'prediction',
        value: formatStreetAddress(feature),
        id: feature.properties.id,
        description: formatStreetAddress(feature),
        index: idx,
        lat: feature.geometry.coordinates[1],
        lng: feature.geometry.coordinates[0],
        'geocode-earth': feature,
      }))

      this._autocompleteCallback(predictionsAsSuggestions, value)
    })
}

export function poweredBy() {

  return (
    <small className="d-flex align-items-center">
      <img src={ GeocodeEarth } width="14" height="14" className="mr-2" />
      <span>© <a href="https://geocode.earth">Geocode Earth</a>, <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>, and <a href="https://geocode.earth/guidelines">others</a></span>
    </small>
  )
}

const featureToAddress = (feature) => ({
  // FIXME Use "geo" key everywhere, and remove
  latitude: feature.geometry.coordinates[1],
  longitude: feature.geometry.coordinates[0],
  geo: {
    latitude: feature.geometry.coordinates[1],
    longitude: feature.geometry.coordinates[0],
  },
  addressCountry: feature.properties.country || '',
  addressLocality: feature.properties.locality || '',
  addressRegion: feature.properties.region || '',
  postalCode: feature.properties.postalcode || '',
  streetAddress: formatStreetAddress(feature),
  isPrecise: Object.prototype.hasOwnProperty.call(feature.properties, 'housenumber'),
  needsGeocoding: false,
})

const formatStreetAddress = (feature) => {

  if (!feature.properties.postalcode) {
    return feature.properties.label
  }

  const parts = [
    feature.properties.name,
    `${feature.properties.postalcode} ${feature.properties.locality}`,
    feature.properties.country,
  ]

  return parts.join(', ')
}

export const transformSuggestion = function (suggestion) {

  const feature = suggestion['geocode-earth']

  return featureToAddress(feature)
}

export const geocode = function (text) {

  return new Promise((resolve) => {

    const latlon = getBoundaryCircleLatlon()
    const [ lat, lon ] = latlon.split(',')

    client.get(`/v1/search?text=${encodeURIComponent(text)}&size=1&api_key=${getApiKey()}&boundary.circle.lat=${lat}&boundary.circle.lon=${lon}`)
      .then(response => {
        if (response.data.features.length > 0) {
          resolve(featureToAddress(response.data.features[0]))
        } else {
          resolve(null)
        }
      })
      .catch(() => resolve(null))
  })
}
