import React from 'react'
import axios from 'axios'
import ngeohash from 'ngeohash'
import _ from 'lodash'

import IdealPostcodes from './ideal-postcodes.svg'

window._paq = window._paq || []

export function placeholder() {

  if (!this.state.postcode) {
    return this.props.t('ENTER_YOUR_POSTCODE')
  }

  return this.props.t('ENTER_YOUR_ADDRESS')
}

export function theme(theme) {

  if (this.state.postcode) {

    return {
      ...theme,
      input: `${theme.input} address-autosuggest__input--addon`
    }
  }

  return theme
}

const toAddress = (postcode) => ({
  latitude: postcode.latitude,
  longitude: postcode.longitude,
  geo: {
    latitude: postcode.latitude,
    longitude: postcode.longitude,
  },
  addressCountry: postcode.country,
  addressLocality: postcode.admin_district || '',
  addressRegion: postcode.region || '',
  postalCode: postcode.postcode,
  geohash: ngeohash.encode(postcode.latitude, postcode.longitude, 11),
  isPrecise: true,
  // streetAddress will be entered *manually*
})

const toPostcode = (address) => ({
  latitude: address.geo ? address.geo.latitude : address.latitude,
  longitude: address.geo ? address.geo.longitude : address.longitude,
  postcode: address.postalCode,
})

export function getInitialState() {

  return {
    value: _.isObject(this.props.address) ?
      (this.props.address.streetAddress || '') : '',
    suggestions: [],
    multiSection: false,
    // @var object
    postcode: _.isObject(this.props.address) && this.props.address.postalCode ?
      toPostcode(this.props.address) : null
  }
}

export function onSuggestionsFetchRequested({ value }) {

  // @see http://postcodes.io/docs

  if (!this.state.postcode) {

    window._paq.push(['trackEvent', 'AddressAutosuggest', 'searchPostcode', value])

    axios({
      method: 'get',
      url: `https://api.postcodes.io/postcodes/${value.replace(/\s/g, '')}/autocomplete`,
    })
      .then(response => {
        if (response.data.status === 200 && Array.isArray(response.data.result)) {
          const suggestions = response.data.result.map(postcode => ({
            type: 'postcode',
            value: postcode,
            address: toAddress(postcode)
          }))
          this.setState({
            suggestions,
          })
        }
      })
  } else {
    this.setState({
      suggestions: [{
        type: 'manual_address',
        value,
      }],
    })
  }
}

export function onSuggestionSelected(event, { suggestion }) {

  // When country = gb
  // This does *NOT* trigger onAddressSelected
  if (suggestion.type === 'postcode') {
    axios({
      method: 'get',
      url: `https://api.postcodes.io/postcodes/${suggestion.value}`,
    })
      .then(response => {
        if (response.data.status === 200 && response.data.result) {
          this.setState({
            value: '',
            postcode: response.data.result,
          })
        }
      })
  }

  if (suggestion.type === 'manual_address') {

    const address = {
      ...toAddress(this.state.postcode),
      streetAddress: suggestion.value,
    }

    this.props.onAddressSelected(suggestion.value, address, suggestion.type)
  }
}

export function poweredBy() {

  return (
    <img src={ IdealPostcodes } />
  )
}

export function highlightFirstSuggestion() {

  return true
}
