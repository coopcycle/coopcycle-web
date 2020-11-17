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

export const onSuggestionsFetchRequested = function({ value }) {

  const searchFunc = getSearch()

  searchFunc({
    query: value,
    type: 'address',
    language: this.language,
    countries: [ this.country ],
    hitsPerPage: 7,
  }).then(results => {

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

    this._autocompleteCallback(predictionsAsSuggestions)
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

  return new Promise((resolve) => {

    searchFunc({
      query: text,
      type: 'address',
      language,
      countries: [ country ],
      hitsPerPage: 1,
    }).then(results => {
      if (results.nbHits > 0) {
        resolve(hitToAddress(results.hits[0], text))
      } else {
        resolve(null)
      }
    }).catch(() => resolve(null))
  })
}
