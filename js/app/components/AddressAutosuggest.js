import React, { Component } from 'react'
import Autosuggest from 'react-autosuggest'
import PropTypes from 'prop-types'
import ngeohash from 'ngeohash'
import Fuse from 'fuse.js'
import { filter, debounce, throttle } from 'lodash'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'
import axios from 'axios'

import '../i18n'
import { getCountry, localeDetector } from '../i18n'

import {
  placeholder as placeholderGB,
  getInitialState as getInitialStateGB,
  onSuggestionsFetchRequested as onSuggestionsFetchRequestedGB,
  onSuggestionSelected as onSuggestionSelectedGB,
  theme as themeGB,
  poweredBy as poweredByGB,
  highlightFirstSuggestion as highlightFirstSuggestionGB } from './AddressAutosuggest/gb'

import {
  onSuggestionsFetchRequested as onSuggestionsFetchRequestedAlgolia,
  poweredBy as poweredByAlgolia,
  transformSuggestion as transformSuggestionAlgolia,
  geocode as geocodeAlgolia,
  } from './AddressAutosuggest/algolia'

import {
  onSuggestionsFetchRequested as onSuggestionsFetchRequestedLocationIQ,
  poweredBy as poweredByLocationIQ,
  transformSuggestion as transformSuggestionLocationIQ,
  geocode as geocodeLocationIQ,
  } from './AddressAutosuggest/locationiq'

import {
  onSuggestionsFetchRequested as onSuggestionsFetchRequestedGE,
  poweredBy as poweredByGE,
  transformSuggestion as transformSuggestionGE,
  geocode as geocodeGE,
  } from './AddressAutosuggest/geocode-earth'

const theme = {
  container:                'react-autosuggest__container address-autosuggest__container',
  containerOpen:            'react-autosuggest__container--open',
  input:                    'react-autosuggest__input address-autosuggest__input',
  inputOpen:                'react-autosuggest__input--open',
  inputFocused:             'react-autosuggest__input--focused',
  suggestionsContainer:     'react-autosuggest__suggestions-container address-autosuggest__suggestions-container',
  suggestionsContainerOpen: 'react-autosuggest__suggestions-container--open address-autosuggest__suggestions-container--open',
  suggestionsList:          'react-autosuggest__suggestions-list',
  suggestion:               'react-autosuggest__suggestion',
  suggestionFirst:          'react-autosuggest__suggestion--first',
  suggestionHighlighted:    'react-autosuggest__suggestion--highlighted',
  sectionContainer:         'react-autosuggest__section-container',
  sectionContainerFirst:    'react-autosuggest__section-container--first',
  sectionTitle:             'react-autosuggest__section-title address-autosuggest__section-title'
}

const defaultFuseOptions = {
  shouldSort: true,
  includeScore: true,
  threshold: 1.0, // We want all addresses, sorted by score
  location: 0,
  distance: 100,
  maxPatternLength: 32,
  minMatchCharLength: 1,
  keys: [
    'streetAddress',
  ]
}

const defaultFuseSearchOptions = {
  limit: 5
}

const localized = {
  gb: {
    placeholder: placeholderGB,
    getInitialState: getInitialStateGB,
    onSuggestionsFetchRequested: onSuggestionsFetchRequestedGB,
    onSuggestionSelected: onSuggestionSelectedGB,
    theme: themeGB,
    poweredBy: poweredByGB,
    highlightFirstSuggestion: highlightFirstSuggestionGB,
  }
}

const adapters = {
  algolia: {
    onSuggestionsFetchRequested: onSuggestionsFetchRequestedAlgolia,
    poweredBy: poweredByAlgolia,
    transformSuggestion: transformSuggestionAlgolia,
    geocode: geocodeAlgolia,
  },
  locationiq: {
    onSuggestionsFetchRequested: onSuggestionsFetchRequestedLocationIQ,
    poweredBy: poweredByLocationIQ,
    transformSuggestion: transformSuggestionLocationIQ,
    geocode: geocodeLocationIQ,
  },
  'geocode-earth': {
    onSuggestionsFetchRequested: onSuggestionsFetchRequestedGE,
    poweredBy: poweredByGE,
    transformSuggestion: transformSuggestionGE,
    geocode: geocodeGE,
  },
}

// WARNING
// Do *NOT* use arrow functions, to allow binding
const generic = {
  placeholder: function() {
    return this.props.placeholder || this.props.t('ENTER_YOUR_ADDRESS')
  },
  getInitialState: function () {

    if (_.isString(this.props.address)) {
      // eslint-disable-next-line no-console
      console.warn('Using a string for the "address" prop is deprecated, use an object instead.')
    }

    let multiSection = false
    const suggestions = []

    if (this.props.addresses.length > 0) {

      multiSection = true

      const addressesAsSuggestions = this.props.addresses.map((address, idx) => ({
        type: 'address',
        value: address.streetAddress,
        address: {
          ...address,
          // Let's suppose saved addresses are precise
          isPrecise: true,
          needsGeocoding: false,
        },
        index: idx,
      }))

      suggestions.push({
        title: this.props.t('SAVED_ADDRESSES'),
        suggestions: addressesAsSuggestions.slice(0, 5),
      })
    }

    return {
      value: _.isObject(this.props.address) ?
        (this.props.address.streetAddress || '') : (_.isString(this.props.address) ? this.props.address : ''),
      suggestions,
      multiSection,
      lastValueWithResults: '',
      loading: false,
    }
  },
  onSuggestionsFetchRequested: function() {
    this.setState({
      suggestions: [],
    })
  },
  onSuggestionSelected: function (event, { suggestion }) {

    if (suggestion.type === 'prediction') {

      let address = this.transformSuggestion(suggestion)

      // If the component was configured for,
      // report validity if the address is not precise enough
      if (this.props.reportValidity && this.props.preciseOnly && (!address.isPrecise && !address.needsGeocoding)) {
        this.autosuggest.input.setCustomValidity(this.props.t('CART_ADDRESS_NOT_ENOUGH_PRECISION'))
        if (HTMLInputElement.prototype.reportValidity) {
          this.autosuggest.input.reportValidity()
        }
      }

      if (address.isPrecise && address.needsGeocoding) {

        this.setState({ loading: true })

        axios
          .get(`/search/geocode?address=${encodeURIComponent(address.streetAddress)}`)
          .then(geocoded => {
            this.setState({ loading: false })
            address = {
              ...address,
              ...geocoded.data,
              geo: geocoded.data,
              geohash: ngeohash.encode(geocoded.data.latitude, geocoded.data.longitude, 11),
              isPrecise: true,
              needsGeocoding: false,
            }
            this.props.onAddressSelected(this.state.value, address, suggestion.type)
          })
          .catch(() => {
            this.setState({ loading: false })
            this.props.onAddressSelected(this.state.value, address, suggestion.type)
          })

      } else {
        address = {
          ...address,
          geohash: ngeohash.encode(address.geo.latitude, address.geo.longitude, 11),
        }
        this.props.onAddressSelected(this.state.value, address, suggestion.type)
      }
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
    }

    if (suggestion.type === 'restaurant') {
      window.location.href = window.Routing.generate('restaurant', {
        id: suggestion.restaurant.id
      })
    }
  },
  transformSuggestion: function(suggestion) {
    return suggestion
  },
  theme: function(theme) {
    return theme
  },
  poweredBy: function() {
    return (
      <span></span>
    )
  },
  highlightFirstSuggestion: function() {
    return false
  }
}

const localize = (func, adapter, thisArg) => {
  if (Object.prototype.hasOwnProperty.call(localized, getCountry())
  &&  Object.prototype.hasOwnProperty.call(localized[getCountry()], func)) {
    return localized[getCountry()][func].bind(thisArg)
  }

  if (Object.prototype.hasOwnProperty.call(adapters, adapter)
  &&  Object.prototype.hasOwnProperty.call(adapters[adapter], func)) {
    return adapters[adapter][func].bind(thisArg)
  }

  return generic[func].bind(thisArg)
}

const getSuggestionValue = suggestion => suggestion.value

const renderSuggestion = suggestion => (
  <div>
    { suggestion.value }
  </div>
)

// https://github.com/moroshko/react-autosuggest#should-render-suggestions-prop
function shouldRenderSuggestions(value) {

  // This allows rendering suggestions for saved adresses
  // when the user just focuses the input without typing anything
  if (value.trimStart().length === 0 && this.state.multiSection) {
    return true
  }

  return value.trimStart().length > 3 || value.trimStart().endsWith(' ')
}

const renderSectionTitle = section => (
  <strong>{ section.title }</strong>
)

const getSectionSuggestions = section => section.suggestions

class AddressAutosuggest extends Component {

  constructor(props) {
    super(props)

    const el = document.getElementById('autocomplete-adapter')
    const adapter = (el && el.dataset.value) || 'algolia'

    this.country = getCountry() || 'en'
    this.language = localeDetector()

    // https://www.peterbe.com/plog/how-to-throttle-and-debounce-an-autocomplete-input-in-react
    this.onSuggestionsFetchRequestedThrottled = throttle(
      localize('onSuggestionsFetchRequested', adapter, this),
      400
    )
    this.onSuggestionsFetchRequestedDebounced = debounce(
      localize('onSuggestionsFetchRequested', adapter, this),
      400
    )

    this.onSuggestionsFetchRequested = ({ value }) => {

      // We still need to check if text is not empty here,
      // because shouldRenderSuggestions() may return true even when nothing was typed
      // This happens when there are saved adresses
      if (value.trimStart().length === 0) {
        return
      }

      if (value.trimStart().length < 5) {
        this.onSuggestionsFetchRequestedThrottled({ value })
      } else {
        this.onSuggestionsFetchRequestedDebounced({ value })
      }
    }

    // Localized methods
    this.getInitialState = localize('getInitialState', adapter, this)
    this.onSuggestionSelected = localize('onSuggestionSelected', adapter, this)
    this.transformSuggestion = localize('transformSuggestion', adapter, this)
    this.placeholder = localize('placeholder', adapter, this)
    this.poweredBy = localize('poweredBy', adapter, this)
    this.theme = localize('theme', adapter, this)
    this.highlightFirstSuggestion = localize('highlightFirstSuggestion', adapter, this)

    this.state = this.getInitialState()
  }

  componentDidMount() {

    const addresses = this.props.addresses.map(address => ({
      ...address,
      // Let's suppose saved addresses are precise
      isPrecise: true,
      needsGeocoding: false,
    }))

    let fuseOptions = { ...defaultFuseOptions }
    if (this.props.fuseOptions) {
      fuseOptions = {
        ...defaultFuseOptions,
        ...this.props.fuseOptions
      }
    }

    this.fuse = new Fuse(addresses, fuseOptions)
    this.fuseForRestaurants = new Fuse(this.props.restaurants, {
      ...fuseOptions,
      threshold: 0.2,
      keys: ['name']
    })

    if (this.props.autofocus) {
      this.autosuggest.input.focus()
    }
  }

  onClear() {
    this.setState({ value: '' })

    if (this.props.reportValidity) {
      this.autosuggest.input.setCustomValidity('')
    }
  }

  onChange(event, { newValue }) {
    this.setState({
      value: newValue
    })

    if (this.props.reportValidity) {
      this.autosuggest.input.setCustomValidity('')
    }
  }

  _autocompleteCallback(predictionsAsSuggestions, value) {

    let suggestions = []
    let multiSection = false

    const { lastValueWithResults } = this.state

    if (this.props.restaurants.length > 0) {

      const restoResults = this.fuseForRestaurants.search(value, {
        ...defaultFuseSearchOptions,
        ...this.props.fuseSearchOptions,
      })

      if (restoResults.length > 0) {

        const restaurantsAsSuggestions = restoResults.map((fuseResult, idx) => ({
          type: 'restaurant',
          value: fuseResult.item.name,
          restaurant: fuseResult.item,
          index: idx,
        }))

        suggestions.push({
          title: this.props.t('RESTAURANTS_AND_STORES'),
          suggestions: restaurantsAsSuggestions
        })
        multiSection = true
      }
    }

    if (this.props.addresses.length > 0) {

      const fuseResults = this.fuse.search(value, {
        ...defaultFuseSearchOptions,
        ...this.props.fuseSearchOptions,
      })

      if (fuseResults.length > 0) {

        const addressesAsSuggestions = fuseResults.map((fuseResult, idx) => ({
          type: 'address',
          value: fuseResult.item.streetAddress,
          address: fuseResult.item,
          index: idx,
        }))

        suggestions.push({
          title: this.props.t('SAVED_ADDRESSES'),
          suggestions: addressesAsSuggestions
        })
        multiSection = true
      }
    }

    if (multiSection) {
      if (predictionsAsSuggestions.length > 0) {
        suggestions.push({
          title: this.props.t('ADDRESS_SUGGESTIONS'),
          suggestions: predictionsAsSuggestions,
        })
      }
    } else {
      suggestions = predictionsAsSuggestions
    }

    // UX optimization
    // When there are no suggestions returned by the autocomplete service,
    // we keep showing the previously returned suggestions.
    // This is useful because some users think they have to type their apartment number
    //
    // Ex:
    // The user types "4 av victoria paris 4" -> 2 suggestions
    // The user types "4 av victoria paris 4 bâtiment B" -> 0 suggestions
    if (predictionsAsSuggestions.length === 0 &&
      lastValueWithResults.length > 0 &&
      value.length > lastValueWithResults.length &&
      value.includes(lastValueWithResults)) {
      return
    }

    this.setState({
      suggestions,
      multiSection,
      lastValueWithResults: predictionsAsSuggestions.length > 0 ? value : ''
    })
  }

  onSuggestionsClearRequested() {

    let suggestions = []
    if (this.props.addresses.length > 0 && this.state.multiSection) {
      suggestions = filter(this.state.suggestions, section => section.title === this.props.t('SAVED_ADDRESSES'))
    }

    this.setState({
      suggestions
    })
  }

  renderInputComponent(inputProps) {

    return (
      <div className="address-autosuggest__input-container">
        <div className="address-autosuggest__input-wrapper">
          <input { ...inputProps } />
          { this.state.value && (
            <button className="address-autosuggest__close-button address-autosuggest__clear" onClick={ () => this.onClear() }>
              <i className="fa fa-times-circle"></i>
            </button>
          )}
        </div>
        { this.state.postcode && (
          <div className="address-autosuggest__addon">
            <span>{ this.state.postcode.postcode }</span>
            <button className="address-autosuggest__close-button" onClick={ () => this.setState({ value: '', postcode: null }) }>
              <i className="fa fa-times-circle"></i>
            </button>
          </div>
        ) }
      </div>
    )
  }

  renderSuggestionsContainer({ containerProps , children }) {

    return (
      <div { ...containerProps }>
        { children }
        <div className="address-autosuggest__suggestions-container__footer">
          <div>
            { this.poweredBy() }
          </div>
        </div>
      </div>
    )
  }

  render() {

    const { value, suggestions, multiSection } = this.state

    let inputProps = {
      placeholder: this.placeholder(),
      value,
      onChange: this.onChange.bind(this),
      type: "search",
      required: this.props.required,
      disabled: this.props.disabled || this.state.loading,
    }

    if (this.props.inputName) {
      inputProps = {
        ...inputProps,
        name: this.props.inputName,
      }
    }

    if (this.props.inputId) {
      inputProps = {
        ...inputProps,
        id: this.props.inputId,
      }
    }

    const highlightFirstSuggestion = this.highlightFirstSuggestion()

    return (
      <Autosuggest
        ref={ autosuggest => this.autosuggest = autosuggest }
        theme={ this.theme(theme) }
        suggestions={ suggestions }
        onSuggestionsFetchRequested={ this.onSuggestionsFetchRequested }
        onSuggestionsClearRequested={ this.onSuggestionsClearRequested.bind(this) }
        onSuggestionSelected={ this.onSuggestionSelected.bind(this) }
        getSuggestionValue={ getSuggestionValue }
        renderInputComponent={ this.renderInputComponent.bind(this) }
        renderSuggestionsContainer={ this.renderSuggestionsContainer.bind(this) }
        renderSuggestion={ renderSuggestion }
        shouldRenderSuggestions={ shouldRenderSuggestions.bind(this) }
        renderSectionTitle={ renderSectionTitle }
        highlightFirstSuggestion={ highlightFirstSuggestion }
        getSectionSuggestions={ getSectionSuggestions }
        multiSection={ multiSection }
        inputProps={ inputProps } />
    )
  }
}

AddressAutosuggest.defaultProps = {
  address: '',
  addresses: [],
  restaurants: [],
  required: false,
  reportValidity: false,
  preciseOnly: false,
  fuseSearchOptions: {},
  disabled: false,
  inputName: undefined,
  inputId: undefined,
}

AddressAutosuggest.propTypes = {
  address: PropTypes.oneOfType([ PropTypes.object, PropTypes.string ]).isRequired,
  addresses: PropTypes.array.isRequired,
  restaurants: PropTypes.array,
  geohash: PropTypes.string.isRequired,
  onAddressSelected: PropTypes.func.isRequired,
  required: PropTypes.bool,
  reportValidity: PropTypes.bool,
  preciseOnly: PropTypes.bool,
  placeholder: PropTypes.string,
  fuseOptions: PropTypes.object,
  fuseSearchOptions: PropTypes.object,
  disabled: PropTypes.bool,
  inputName: PropTypes.string,
  inputId: PropTypes.string,
}

export default withTranslation()(AddressAutosuggest)

export const geocode = (text) => {

  const el = document.getElementById('autocomplete-adapter')
  const adapter = (el && el.dataset.value) || 'algolia'

  return new Promise((resolve) => {
    localize('geocode', adapter, null)(text, (getCountry() || 'en'), localeDetector())
      .then(address => {

        if (!address || (address.isPrecise && !address.needsGeocoding)) {
          return resolve(address)
        }

        axios
          .get(`/search/geocode?address=${encodeURIComponent(address.streetAddress)}`)
          .then(geocoded => {
            resolve({
              ...address,
              ...geocoded.data,
              geo: geocoded.data,
              geohash: ngeohash.encode(geocoded.data.latitude, geocoded.data.longitude, 11),
              isPrecise: true,
              needsGeocoding: false,
            })
          })
          .catch(() => {
            resolve(address)
          })
      })
  })
}
