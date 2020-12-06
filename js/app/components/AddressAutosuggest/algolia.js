import React from 'react'

// https://www.algolia.com/doc/api-client/getting-started/upgrade-guides/javascript/#the-initplaces-method
import algoliasearch from 'algoliasearch/lite'
import { shuffle } from '@algolia/client-common'

import PoweredByAlgolia from './algolia.svg'

let search = null
const getSearch = function() {
  if (null === search) {
    search = initSearch()
  }

  return search
}

let aroundLatLng = null
function getAroundLatLng() {
  if (null === aroundLatLng) {
    const el = document.getElementById('algolia-places')
    if (el) {
      aroundLatLng = el.dataset.aroundLatLng
    }
  }

  return aroundLatLng
}

let addressTemplate = null
function getAddressTemplate() {
  if (null === addressTemplate) {
    const el = document.getElementById('algolia-places')
    if (el) {
      addressTemplate = el.dataset.addressTemplate
    } else {
      addressTemplate = 'city'
    }
  }

  return addressTemplate
}

export const places = (appId = '', apiKey = '', options) => {
  const placesClient = algoliasearch(appId, apiKey, {
    hosts: [{ url: 'places-dsn.algolia.net' }].concat(
      shuffle([
        { url: 'places-1.algolia.net' },
        { url: 'places-2.algolia.net' },
        { url: 'places-3.algolia.net' }
      ])
    ),
    ...options
  });
  return (data, requestOptions) => {
    return placesClient.transporter.read(
      {
        method: 'POST',
        path: '1/places/query',
        data,
        cacheable: true
      },
      requestOptions
    );
  };
}

const initSearch = () => {
  const el = document.getElementById('algolia-places')
  if (el) {
    return places(el.dataset.appId, el.dataset.apiKey)
  }
}

/* Exported to make it testable */
export const formatAddress = (hit, template) => {

  template = template || getAddressTemplate()

  const parts = [
    hit.locale_names[0]
  ]

  if (hit.postcode && hit.postcode[0]) {
    if (template === 'county') {
      parts.push(`${hit.postcode[0]} ${hit.county[0]}`)
    } else {
      parts.push(`${hit.postcode[0]} ${hit.city[0]}`)
    }
  } else {
    if (template === 'county') {
      parts.push(hit.county[0])
    } else {
      parts.push(hit.city[0])
    }
  }

  parts.push(hit.country)

  return parts.join(', ')
}

// https://community.algolia.com/places/api-clients.html#json-answer
const hitToAddress = (hit, value = '') => {

  const streetAddress = value ?
    value : formatAddress(hit)

  return {
    // FIXME Use "geo" key everywhere, and remove
    latitude: hit._geoloc.lat,
    longitude: hit._geoloc.lng,
    geo: {
      latitude: hit._geoloc.lat,
      longitude: hit._geoloc.lng,
    },
    addressCountry: hit.country || '',
    addressLocality: hit.city[0] || '',
    addressRegion: hit.administrative[0] || '',
    postalCode: hit.postcode[0] || '',
    streetAddress,
    // https://community.algolia.com/places/examples.html#using-_rankinginfo
    // By default, Places only offers precision up to the street level,
    // which means that all the house numbers of a street will have the same geolocation.
    // However, Places offers house level precision in France
    isPrecise: Object.prototype.hasOwnProperty.call(hit._rankingInfo, 'roadNumberPrecision'),
    needsGeocoding: Object.prototype.hasOwnProperty.call(hit._rankingInfo, 'roadNumberPrecision') && hit._rankingInfo.roadNumberPrecision === 'centroid',
  }
}

export const onSuggestionsFetchRequested = function({ value }) {

  const searchFunc = getSearch()
  const aroundLatLngValue = getAroundLatLng()

  let searchParams = {
    query: value,
    type: 'address',
    language: this.language,
    hitsPerPage: 5,
    getRankingInfo: true,
  }

  if (aroundLatLngValue) {
    searchParams = {
      ...searchParams,
      aroundLatLng: aroundLatLngValue,
      aroundRadius: 50000,
    }
  } else {
    searchParams = {
      ...searchParams,
      countries: [ this.country ],
    }
  }

  searchFunc(searchParams).then(results => {

    const predictionsAsSuggestions = results.hits.map((hit, idx) => ({
      type: 'prediction',
      value: formatAddress(hit),
      id: hit.objectID,
      description: formatAddress(hit),
      index: idx,
      lat: hit._geoloc.lat,
      lng: hit._geoloc.lng,
      hit,
    }))

    this._autocompleteCallback(predictionsAsSuggestions, value)
  })
}

export function poweredBy() {

  return (
    <img src={ PoweredByAlgolia } />
  )
}

export const transformSuggestion = function (suggestion) {

  return hitToAddress(suggestion.hit)
}

export const geocode = function (text, country = 'en', language = 'en') {

  const searchFunc = getSearch()
  const aroundLatLngValue = getAroundLatLng()

  return new Promise((resolve) => {

    let searchParams = {
      query: text,
      type: 'address',
      language,
      hitsPerPage: 1,
      getRankingInfo: true,
    }

    if (aroundLatLngValue) {
      searchParams = {
        ...searchParams,
        aroundLatLng: aroundLatLngValue,
        aroundRadius: 50000,
      }
    } else {
      searchParams = {
        ...searchParams,
        countries: [ country ],
      }
    }

    searchFunc(searchParams).then(results => {
      if (results.nbHits > 0) {
        resolve(hitToAddress(results.hits[0], text))
      } else {
        resolve(null)
      }
    }).catch(() => resolve(null))
  })
}
