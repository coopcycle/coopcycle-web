import React from 'react'

import { hitToAddress, initSearch } from '../../utils/algolia'
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
      value: `${hit.locale_names[0]}, ${hit.city[0]}, ${hit.country}`,
      id: hit.objectID,
      description: `${hit.locale_names[0]}, ${hit.city[0]}, ${hit.country}`,
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
