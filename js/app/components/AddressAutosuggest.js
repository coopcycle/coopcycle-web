import React, { Component } from 'react'
import Autosuggest from 'react-autosuggest'
import PropTypes from 'prop-types'
import ngeohash from 'ngeohash'
import Fuse from 'fuse.js'
import { includes, filter, debounce } from 'lodash'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'

import '../i18n'
import { getCountry } from '../i18n'

import { placeToAddress } from '../utils/GoogleMaps'
import PoweredByGoogle from './powered_by_google_on_white.png'
import {
  placeholder as placeholderGB,
  getInitialState as getInitialStateGB,
  onSuggestionsFetchRequested as onSuggestionsFetchRequestedGB,
  onSuggestionSelected as onSuggestionSelectedGB,
  theme as themeGB,
  poweredBy as poweredByGB,
  highlightFirstSuggestion as highlightFirstSuggestionGB } from './AddressAutosuggest/gb'

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

const autocompleteOptions = {
  types: ['address'],
  componentRestrictions: {
    country: getCountry() || 'fr'
  }
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

    return {
      value: _.isObject(this.props.address) ?
        (this.props.address.streetAddress || '') : (_.isString(this.props.address) ? this.props.address : ''),
      suggestions: [],
      multiSection: false,
    }
  },
  onSuggestionsFetchRequested: function({ value }) {
    // @see https://developers.google.com/maps/documentation/javascript/places-autocomplete#place_autocomplete_service
    // @see https://developers.google.com/maps/documentation/javascript/reference/#AutocompleteService
    this.autocompleteService.getPlacePredictions({
      ...autocompleteOptions,
      input: value,
    }, this._autocompleteCallback.bind(this))
  },
  onSuggestionSelected: function (event, { suggestion }) {

    if (suggestion.type === 'prediction') {
      const { placeId } = suggestion

      this.geocoder.geocode({ placeId }, (results, status) => {
        if (status === this.geocoderOK && results.length === 1) {

          const place = results[0]
          const lat = place.geometry.location.lat()
          const lng = place.geometry.location.lng()
          const geohash = ngeohash.encode(lat, lng, 11)

          const address = {
            ...placeToAddress(place, this.state.value),
            geohash,
          }

          // If the component was configured for,
          // report validity if the address is not precise enough
          if (this.props.reportValidity && this.props.preciseOnly && !address.isPrecise) {
            this.autosuggest.input.setCustomValidity(this.props.t('CART_ADDRESS_NOT_ENOUGH_PRECISION'))
            if (HTMLInputElement.prototype.reportValidity) {
              this.autosuggest.input.reportValidity()
            }
          }

          this.props.onAddressSelected(this.state.value, address, suggestion.type)
        }
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
    }
  },
  theme: function(theme) {
    return theme
  },
  poweredBy: function() {
    return (
      <img src={ PoweredByGoogle } />
    )
  },
  highlightFirstSuggestion: function() {
    return false
  }
}

const localize = (func, thisArg) => {
  if (Object.prototype.hasOwnProperty.call(localized, getCountry())
  &&  Object.prototype.hasOwnProperty.call(localized[getCountry()], func)) {
    return localized[getCountry()][func].bind(thisArg)
  }

  return generic[func].bind(thisArg)
}

const getSuggestionValue = suggestion => suggestion.value

const renderSuggestion = suggestion => (
  <div>
    { suggestion.value }
  </div>
)

const shouldRenderSuggestions = value => value.trim().length > 3

const renderSectionTitle = section => (
  <strong>{ section.title }</strong>
)

const getSectionSuggestions = section => section.suggestions

class AddressAutosuggest extends Component {

  constructor(props) {
    super(props)

    // Localized methods
    this.onSuggestionsFetchRequested = debounce(
      localize('onSuggestionsFetchRequested', this),
      350
    )
    this.getInitialState = localize('getInitialState', this)
    this.onSuggestionSelected = localize('onSuggestionSelected', this)
    this.placeholder = localize('placeholder', this)
    this.poweredBy = localize('poweredBy', this)
    this.theme = localize('theme', this)
    this.highlightFirstSuggestion = localize('highlightFirstSuggestion', this)

    this.state = this.getInitialState()
  }

  componentDidMount() {

    if (!window.google) {
      throw new Error(
        'Google Maps JavaScript API library must be loaded.'
      )
    }

    if (!window.google.maps.places) {
      throw new Error(
        'Google Maps Places library must be loaded. Please add `libraries=places` to the src URL.'
      )
    }

    this.autocompleteService = new window.google.maps.places.AutocompleteService()
    this.autocompleteOK = window.google.maps.places.PlacesServiceStatus.OK
    this.autocompleteZeroResults = window.google.maps.places.PlacesServiceStatus.ZERO_RESULTS

    this.geocoder = new window.google.maps.Geocoder()
    this.geocoderOK = window.google.maps.GeocoderStatus.OK

    const addresses = this.props.addresses.map(address => ({
      ...address,
      isPrecise: true, // Let's suppose saved addresses are precise
    }))

    let fuseOptions = { ...defaultFuseOptions }
    if (this.props.fuseOptions) {
      fuseOptions = {
        ...defaultFuseOptions,
        ...this.props.fuseOptions
      }
    }

    this.fuse = new Fuse(addresses, fuseOptions)

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

  _autocompleteCallback(predictions, status) {

    let suggestions = []

    let predictionsAsSuggestions = []
    if (status === this.autocompleteOK && Array.isArray(predictions)) {
      predictionsAsSuggestions = predictions.map((p, idx) => ({
        type: 'prediction',
        value: p.description,
        id: p.id,
        description: p.description,
        placeId: p.place_id,
        index: idx,
        matchedSubstrings: p.matched_substrings,
        terms: p.terms,
        types: p.types,
      }))
    }

    let multiSection = false

    if (this.props.addresses.length > 0) {

      const { value } = this.state

      const fuseResults = this.fuse.search(value, {
        ...defaultFuseSearchOptions,
        ...this.props.fuseSearchOptions,
      })

      if (fuseResults.length > 0) {

        multiSection = true

        const addressesAsSuggestions = fuseResults.map((fuseResult, idx) => ({
          type: 'address',
          value: fuseResult.item.streetAddress,
          address: fuseResult.item,
          index: idx,
        }))

        const addressesValues = addressesAsSuggestions.map(suggestion => suggestion.value)

        suggestions.push({
          title: this.props.t('SAVED_ADDRESSES'),
          suggestions: addressesAsSuggestions
        })

        predictionsAsSuggestions =
          filter(predictionsAsSuggestions, suggestion => !includes(addressesValues, suggestion.value))

        if (predictionsAsSuggestions.length > 0) {
          suggestions.push({
            title: this.props.t('ADDRESS_SUGGESTIONS'),
            suggestions: filter(predictionsAsSuggestions, suggestion => !includes(addressesValues, suggestion.value))
          })
        }
      } else {
        suggestions = predictionsAsSuggestions
      }
    } else {
      suggestions = predictionsAsSuggestions
    }

    this.setState({
      suggestions,
      multiSection,
    })
  }

  onSuggestionsClearRequested() {
    this.setState({
      suggestions: []
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
      disabled: this.props.disabled,
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
        shouldRenderSuggestions={ shouldRenderSuggestions }
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
