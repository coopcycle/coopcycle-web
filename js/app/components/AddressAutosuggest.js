import React, { Component } from 'react'
import Autosuggest from 'react-autosuggest'
import PropTypes from 'prop-types'
import ngeohash from 'ngeohash'
import Fuse from 'fuse.js'
import { includes, filter, debounce } from 'lodash'

import i18n from '../i18n'
import { placeToAddress } from '../utils/GoogleMaps'

const autocompleteOptions = {
  types: ['address'],
  componentRestrictions: {
    country: window.AppData.countryIso || 'fr'
  }
}

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

    this.state = {
      value: props.address,
      suggestions: [],
      multiSection: false,
    }

    this.onSuggestionsFetchRequested = debounce(this.onSuggestionsFetchRequested.bind(this), 350)
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
          title: i18n.t('SAVED_ADDRESSES'),
          suggestions: addressesAsSuggestions
        })

        predictionsAsSuggestions =
          filter(predictionsAsSuggestions, suggestion => !includes(addressesValues, suggestion.value))

        if (predictionsAsSuggestions.length > 0) {
          suggestions.push({
            title: i18n.t('ADDRESS_SUGGESTIONS'),
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

  onSuggestionsFetchRequested({ value }) {
    // @see https://developers.google.com/maps/documentation/javascript/places-autocomplete#place_autocomplete_service
    // @see https://developers.google.com/maps/documentation/javascript/reference/#AutocompleteService
    this.autocompleteService.getPlacePredictions({
      ...autocompleteOptions,
      input: value,
    }, this._autocompleteCallback.bind(this))
  }

  onSuggestionsClearRequested() {
    this.setState({
      suggestions: []
    })
  }

  onSuggestionSelected(event, { suggestion }) {

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
            this.autosuggest.input.setCustomValidity(i18n.t('CART_ADDRESS_NOT_ENOUGH_PRECISION'))
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
  }

  renderInputComponent(inputProps) {

    return (
      <div>
        <input { ...inputProps } />
        { this.state.value && (
          <button className="address-autosuggest__clear" onClick={ () => this.onClear() }>
            <i className="fa fa-times-circle"></i>
          </button>
        )}
      </div>
    )
  }

  renderSuggestionsContainer({ containerProps , children }) {

    return (
      <div { ...containerProps }>
        { children }
        <div className="address-autosuggest__suggestions-container__footer">
          <div>
            <img src={ require('./powered_by_google_on_white.png') } />
          </div>
        </div>
      </div>
    )
  }

  render() {

    const { value, suggestions, multiSection } = this.state

    const inputProps = {
      placeholder: this.props.placeholder,
      value,
      onChange: this.onChange.bind(this),
      type: "search",
      required: this.props.required
    }

    return (
      <Autosuggest
        ref={ autosuggest => this.autosuggest = autosuggest }
        theme={ theme }
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
        getSectionSuggestions={ getSectionSuggestions }
        multiSection={ multiSection }
        inputProps={ inputProps } />
    )
  }
}

AddressAutosuggest.defaultProps = {
  addresses: [],
  required: false,
  reportValidity: false,
  preciseOnly: false,
  placeholder: i18n.t('ENTER_YOUR_ADDRESS'),
  fuseSearchOptions: {},
}

AddressAutosuggest.propTypes = {
  address: PropTypes.string.isRequired,
  addresses: PropTypes.array.isRequired,
  geohash: PropTypes.string.isRequired,
  onAddressSelected: PropTypes.func.isRequired,
  required: PropTypes.bool,
  reportValidity: PropTypes.bool,
  preciseOnly: PropTypes.bool,
  placeholder: PropTypes.string,
  fuseOptions: PropTypes.object,
  fuseSearchOptions: PropTypes.object,
}

export default AddressAutosuggest
