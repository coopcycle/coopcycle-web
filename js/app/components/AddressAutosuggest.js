import React, { Component } from 'react'
import Autosuggest from 'react-autosuggest'
import PropTypes from 'prop-types'
import ngeohash from 'ngeohash'
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
  sectionTitle:             'react-autosuggest__section-title'
}

const getSuggestionValue = suggestion => suggestion.description

const renderSuggestion = suggestion => (
  <div>
    { suggestion.description }
  </div>
)

class AddressAutosuggest extends Component {

  constructor(props) {
    super(props)

    this.state = {
      value: props.address,
      suggestions: []
    }
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

    this.geocoder = new window.google.maps.Geocoder()
    this.geocoderOK = window.google.maps.GeocoderStatus.OK

    if (this.props.autofocus) {
      this.autosuggest.input.focus()
    }
  }

  onChange(event, { newValue }) {
    this.setState({
      value: newValue
    })
  }

  _autocompleteCallback(predictions, status) {
    this.setState({
      suggestions: predictions.map((p, idx) => ({
        id: p.id,
        description: p.description,
        placeId: p.place_id,
        index: idx,
        matchedSubstrings: p.matched_substrings,
        terms: p.terms,
        types: p.types,
      })),
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

        this.props.onAddressSelected(this.state.value, address)
      }
    })
  }

  renderInputComponent(inputProps) {

    return (
      <div>
        <input { ...inputProps } />
        { this.state.value && (
          <button className="address-autosuggest__clear" onClick={ () => this.setState({ value: '' }) }>
            <i className="fa fa-times-circle"></i>
          </button>
        )}
      </div>
    )
  }

  renderSuggestionsContainer({ containerProps , children, query }) {

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

    const { value, suggestions } = this.state

    const inputProps = {
      placeholder: i18n.t('ENTER_YOUR_ADDRESS'),
      value,
      onChange: this.onChange.bind(this)
    }

    return (
      <Autosuggest
        ref={ autosuggest => this.autosuggest = autosuggest }
        theme={ theme }
        suggestions={ suggestions }
        onSuggestionsFetchRequested={ this.onSuggestionsFetchRequested.bind(this) }
        onSuggestionsClearRequested={ this.onSuggestionsClearRequested.bind(this) }
        onSuggestionSelected={ this.onSuggestionSelected.bind(this) }
        getSuggestionValue={ getSuggestionValue }
        renderInputComponent={ this.renderInputComponent.bind(this) }
        renderSuggestionsContainer={ this.renderSuggestionsContainer.bind(this) }
        renderSuggestion={ renderSuggestion }
        inputProps={ inputProps }
      />
    )
  }
}

AddressAutosuggest.propTypes = {
  address: PropTypes.string.isRequired,
  geohash: PropTypes.string.isRequired,
  onAddressSelected: PropTypes.func.isRequired,
}

export default AddressAutosuggest
