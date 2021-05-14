import React, { Component } from 'react'
import { createPortal } from 'react-dom'
import Autosuggest from 'react-autosuggest'
import { defaultTheme } from 'react-autosuggest/dist/theme'
import PropTypes from 'prop-types'
import ngeohash from 'ngeohash'
import Fuse from 'fuse.js'
import { filter, debounce, throttle } from 'lodash'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'
import axios from 'axios'
import classNames from 'classnames'

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
  configure as configureAlgolia
  } from './AddressAutosuggest/algolia'

import {
  onSuggestionsFetchRequested as onSuggestionsFetchRequestedLocationIQ,
  poweredBy as poweredByLocationIQ,
  transformSuggestion as transformSuggestionLocationIQ,
  geocode as geocodeLocationIQ,
  configure as configureLocationIQ,
  } from './AddressAutosuggest/locationiq'

import {
  onSuggestionsFetchRequested as onSuggestionsFetchRequestedGE,
  poweredBy as poweredByGE,
  transformSuggestion as transformSuggestionGE,
  geocode as geocodeGE,
  configure as configureGE
  } from './AddressAutosuggest/geocode-earth'

import { storage, getFromCache } from './AddressAutosuggest/cache'
import { getAdapter, getAdapterOptions } from './AddressAutosuggest/config'

const theme = {
  ...defaultTheme,
  container:                `${defaultTheme.container} address-autosuggest__container`,
  input:                    `${defaultTheme.input} address-autosuggest__input`,
  suggestionsContainer:     `${defaultTheme.suggestionsContainer} address-autosuggest__suggestions-container`,
  suggestionsContainerOpen: `${defaultTheme.suggestionsContainerOpen} address-autosuggest__suggestions-container--open`,
  sectionTitle:             `${defaultTheme.sectionTitle} address-autosuggest__section-title`
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
    useCache: function () {
      return true
    },
    configure: configureAlgolia,
  },
  locationiq: {
    onSuggestionsFetchRequested: onSuggestionsFetchRequestedLocationIQ,
    poweredBy: poweredByLocationIQ,
    transformSuggestion: transformSuggestionLocationIQ,
    geocode: geocodeLocationIQ,
    configure: configureLocationIQ,
  },
  'geocode-earth': {
    onSuggestionsFetchRequested: onSuggestionsFetchRequestedGE,
    poweredBy: poweredByGE,
    transformSuggestion: transformSuggestionGE,
    geocode: geocodeGE,
    configure: configureGE,
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
  },
  useCache: function() {
    return false
  },
  configure: function() {},
}

const localize = (func, adapter, thisArg) => {
  if (Object.prototype.hasOwnProperty.call(localized, thisArg.country)
  &&  Object.prototype.hasOwnProperty.call(localized[thisArg.country], func)) {
    return localized[thisArg.country][func].bind(thisArg)
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

const SuggestionsContainer = ({ containerProps, children, poweredBy }) => (
  <div { ...containerProps }>
    { children }
    <div className="address-autosuggest__suggestions-container__footer">
      <div>
        { poweredBy }
      </div>
    </div>
  </div>
)

class AddressAutosuggest extends Component {

  constructor(props) {
    super(props)

    this.country = props.country || getCountry() || 'en'
    this.language = props.language || localeDetector()

    const adapter = getAdapter(props, document)
    const adapterOptions = getAdapterOptions(props, document)

    const configure = localize('configure', adapter, this)
    configure(adapterOptions[adapter])

    const onSuggestionsFetchRequestedBase =
      localize('onSuggestionsFetchRequested', adapter, this)

    const onSuggestionsFetchRequestedCached = ({ value }) => {

      if (!this.useCache()) {
        onSuggestionsFetchRequestedBase({ value })
        return
      }

      const cached = storage.get(value)
      if (cached && Array.isArray(cached)) {
        this._autocompleteCallback(cached, value)
      } else {
        onSuggestionsFetchRequestedBase({ value })
      }
    }

    // https://www.peterbe.com/plog/how-to-throttle-and-debounce-an-autocomplete-input-in-react
    this.onSuggestionsFetchRequestedThrottled = throttle(
      onSuggestionsFetchRequestedCached,
      400
    )
    this.onSuggestionsFetchRequestedDebounced = debounce(
      onSuggestionsFetchRequestedCached,
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
    this.useCache = localize('useCache', adapter, this)

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
      setTimeout(() => this.autosuggest?.input.focus(), 150)
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

  _autocompleteCallback(predictionsAsSuggestions, value, cache = false) {

    let suggestions = []
    let multiSection = false

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

    // Cache results
    if (predictionsAsSuggestions.length > 0 && cache) {
      storage.set(value, predictionsAsSuggestions, new Date().getTime() + (5 * 60 * 1000)) // Cache for 5 minutes
    }

    // UX optimization
    // When there are no suggestions returned by the autocomplete service,
    // we keep showing the previously returned suggestions.
    // This is useful because some users think they have to type their apartment number
    //
    // Ex:
    // The user types "4 av victoria paris 4" -> 2 suggestions
    // The user types "4 av victoria paris 4 bâtiment B" -> 0 suggestions
    if (predictionsAsSuggestions.length === 0 && this.useCache()) {
      const cachedResults = getFromCache(value)
      if (cachedResults.length > 0) {
        predictionsAsSuggestions = cachedResults
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

    this.setState({
      suggestions,
      multiSection,
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
      <div className={ classNames({
        'address-autosuggest__input-container': true,
        'has-error': this.props.error
        })}>
        <div className="address-autosuggest__input-wrapper">
          <input { ...inputProps } />
          { this.state.postcode && (
            <div className="address-autosuggest__addon">
              <span>{ this.state.postcode.postcode }</span>
              <button className="address-autosuggest__close-button" onClick={ () => this.setState({ value: '', postcode: null }) }>
                <i className="fa fa-times-circle"></i>
              </button>
            </div>
          ) }
          { this.state.value && (
            <button className="address-autosuggest__close-button address-autosuggest__clear" onClick={ () => this.onClear() }>
              <i className="fa fa-times-circle"></i>
            </button>
          )}
        </div>
      </div>
    )
  }

  renderSuggestionsContainer({ containerProps , children }) {

    // https://github.com/moroshko/react-autosuggest/issues/699#issuecomment-568798287
    if (this.props.attachToBody && this.autosuggest) {

      // this.input is the input ref as received from Autosuggest
      const inputCoords = this.autosuggest.input.getBoundingClientRect()
      const style = {
        position: 'absolute',
        left: inputCoords.left + window.scrollX, // adding scrollX and scrollY to get the coords wrt document instead of viewport
        top: inputCoords.top + inputCoords.height + window.scrollY,
        overflow: 'auto',
        zIndex: 4,
        backgroundColor: '#ffffff',
        width: inputCoords.width,
      }

      return createPortal((
        <SuggestionsContainer
          containerProps={{
            ...containerProps,
            style
          }}
          poweredBy={ this.poweredBy() }>
          { children }
        </SuggestionsContainer>
      ), document.body)
    }

    return (
      <SuggestionsContainer
        containerProps={ containerProps }
        poweredBy={ this.poweredBy() }>
        { children }
      </SuggestionsContainer>
    )
  }

  render() {

    const { value, suggestions, multiSection } = this.state

    const inputProps = {
      placeholder: this.placeholder(),
      value,
      onChange: this.onChange.bind(this),
      type: "search",
      required: this.props.required,
      disabled: this.props.disabled || this.state.loading,
      // FIXME
      // We may override important props such as value, onChange
      // We need to omit some props
      ...this.props.inputProps,
    }

    const highlightFirstSuggestion = this.highlightFirstSuggestion()

    let otherProps = {}
    if (Object.prototype.hasOwnProperty.call(this.props, 'id')) {
      otherProps = {
        id: this.props.id
      }
    }

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
        inputProps={ inputProps }
        containerProps={ this.props.containerProps }
        { ...otherProps } />
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
  geohash: '',
  containerProps: {},
  attachToBody: false,
  onAddressSelected: () => {},
  inputProps: {},
  autofocus: false,
  error: false,
}

AddressAutosuggest.propTypes = {
  address: PropTypes.oneOfType([ PropTypes.object, PropTypes.string ]).isRequired,
  addresses: PropTypes.array.isRequired,
  restaurants: PropTypes.array,
  geohash: PropTypes.string,
  onAddressSelected: PropTypes.func.isRequired,
  required: PropTypes.bool,
  reportValidity: PropTypes.bool,
  preciseOnly: PropTypes.bool,
  placeholder: PropTypes.string,
  fuseOptions: PropTypes.object,
  fuseSearchOptions: PropTypes.object,
  disabled: PropTypes.bool,
  containerProps: PropTypes.object,
  attachToBody: PropTypes.bool,
  inputProps: PropTypes.object,
  autofocus: PropTypes.bool,
  error: PropTypes.bool,
}

export default withTranslation()(AddressAutosuggest)

export const geocode = (text) => {

  const adapter = getAdapter({}, document)
  const adapterOptions = getAdapterOptions({}, document)

  const fakeThis = {
    country: getCountry() || 'en'
  }

  const configure = localize('configure', adapter, fakeThis)
  configure(adapterOptions[adapter])

  return new Promise((resolve) => {

    localize('geocode', adapter, fakeThis)(text, (getCountry() || 'en'), localeDetector())
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
              needsGeocoding: false,
            })
          })
          .catch(() => {
            resolve(address)
          })
      })
  })
}
