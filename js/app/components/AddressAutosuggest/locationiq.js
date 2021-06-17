import React from 'react'
import axios from 'axios'
import qs from 'qs'

import LocationIQ from './locationiq.png'

const client = axios.create({
  baseURL: 'https://api.locationiq.com',
})

let accessToken = null
function getAccessToken() {

  return accessToken
}

let viewbox = null
function getViewbox() {

  return viewbox
}

const getFormattedAddress = (result) => {
  if (result.type === 'house') {

    return result.postal_address
  }

  // When the house number is missing,
  // LocationIQ returns results with too much useless details
  // For ex, when you type "calle de toledo", it returns
  //
  // Calle de Toledo, Embajadores, Madrid, Comunidad de Madrid, 28001, España
  return [
    result.address.name,
    `${result.address.postcode} ${result.address.city || result.address.state}`
  ].join(', ')
}

const getSearchParams = (q, country, language) => ({
  key: getAccessToken(),
  q: q.substring(0, 200),
  'accept-language': language,
  dedupe: '1',
  limit: '5',
  // FIXME
  // This can be useful to have addresses formatted for country,
  // but it doesn't work when entering only the street name
  postaladdress: '1',
  viewbox: getViewbox(),
  bounded: '1',
  tag: 'highway:*,place:*',
})

export const onSuggestionsFetchRequested = function({ value }) {

  const params = getSearchParams(value, this.country, this.language)

  // @see https://github.com/osm-search/Nominatim/blob/80df4d3b560f5b1fd550dcf8cdc09a992b69fee0/settings/partitionedtags.def
  client.get(`/v1/autocomplete.php?${qs.stringify(params)}`)
    .then(response => {

      const predictionsAsSuggestions = response.data.map((result, idx) => ({
        type: 'prediction',
        value: getFormattedAddress(result),
        id: result.place_id,
        description: getFormattedAddress(result),
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
  streetAddress: getFormattedAddress(locationiq),
  isPrecise: locationiq.type === 'house' || Object.prototype.hasOwnProperty.call(locationiq.address, 'house_number'),
  needsGeocoding: false,
})

export const transformSuggestion = function (suggestion) {

  const locationiq = suggestion.locationiq

  return locationiqToAddress(locationiq)
}

export const geocode = function (text, country = 'en', language = 'en') {

  return new Promise((resolve) => {

    const params = getSearchParams(text, country, language)

    client.get(`/v1/autocomplete.php?${qs.stringify(params)}`)
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

export const configure = function (options) {
  accessToken = options.accessToken
  viewbox = options.viewbox
}
