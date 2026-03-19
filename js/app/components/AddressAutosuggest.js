import React, { Component } from 'react'
import { createPortal } from 'react-dom'
import Autosuggest from 'react-autosuggest'
import { defaultTheme } from 'react-autosuggest/dist/theme'
import PropTypes from 'prop-types'
import ngeohash from 'ngeohash'
import Fuse from 'fuse.js'
import { filter, debounce, throttle } from 'lodash'
import { withTranslation, useTranslation } from 'react-i18next'
import _ from 'lodash'
import axios from 'axios'
import clsx from 'clsx'
import { OpenLocationCode } from 'open-location-code'

import '../../../assets/css/address-autosuggest.css'

import '../i18n'
import { getCountry, localeDetector } from '../i18n'

import {
  placeholder as placeholderGB,
  getInitialState as getInitialStateGB,
  onSuggestionsFetchRequested as onSuggestionsFetchRequestedGB,
  onSuggestionSelected as onSuggestionSelectedGB,
  theme as themeGB,
  poweredBy as poweredByGB,
  highlightFirstSuggestion as highlightFirstSuggestionGB,
} from './AddressAutosuggest/gb'

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
  configure as configureGE,
} from './AddressAutosuggest/geocode-earth'

import {
  onSuggestionsFetchRequested as onSuggestionsFetchRequestedGOOG,
  poweredBy as poweredByGOOG,
  transformSuggestion as transformSuggestionGOOG,
  geocode as geocodeGOOG,
  configure as configureGOOG,
  onSuggestionSelected as onSuggestionSelectedGOOG,
} from './AddressAutosuggest/google'

import { storage, getFromCache } from './AddressAutosuggest/cache'
import { getAdapter, getAdapterOptions } from './AddressAutosuggest/config'
import MapPicker from './MapPicker'

const theme = {
  ...defaultTheme,
  container: `tw:relative`,
  input: ``, // The styles are in renderInputComponent
  suggestionsContainer: `tw:absolute tw:left-0 tw:right-0 tw:z-2000`,
  suggestionsContainerOpen: `tw:bg-base-100 tw:border-1 tw:border-base-300 tw:rounded-b-lg`,
  suggestion: `tw:p-2 tw:cursor-pointer`,
  suggestionHighlighted: 'tw:bg-base-300',
  sectionTitle: `tw:px-2 tw:py-2.5`,
}

const defaultFuseOptions = {
  shouldSort: true,
  includeScore: true,
  threshold: 1.0, // We want all addresses, sorted by score
  location: 0,
  distance: 100,
  maxPatternLength: 32,
  minMatchCharLength: 1,
  keys: ['streetAddress', 'name'],
}

const defaultFuseSearchOptions = {
  limit: 5,
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
  },
}

const adapters = {
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
  google: {
    onSuggestionsFetchRequested: onSuggestionsFetchRequestedGOOG,
    poweredBy: poweredByGOOG,
    transformSuggestion: transformSuggestionGOOG,
    geocode: geocodeGOOG,
    configure: configureGOOG,
    onSuggestionSelected: onSuggestionSelectedGOOG,
  }
}

const openLocationCode = new OpenLocationCode()

// Lazy compute coordinates
// Needed to avoid func exec before DOM is ready
const coordinates = {
  get value() {
    delete this.value
    const element = document.getElementById('cpccl_settings')
    if (!element) return (this.value = [0, 0])

    try {
      const [lat, lng] = JSON.parse(element.dataset.latlng)
        .split(',')
        .map(s => parseFloat(s.trim()))
      return (this.value = [lat, lng])
    } catch {
      return (this.value = [0, 0])
    }
  },
}



// WARNING
// Do *NOT* use arrow functions, to allow binding
const generic = {
  placeholder: function() {
    return this.props.placeholder || this.props.t('ENTER_YOUR_ADDRESS')
  },
  getInitialState: function() {
    if (_.isString(this.props.address)) {
      // eslint-disable-next-line no-console
      console.warn(
        'Using a string for the "address" prop is deprecated, use an object instead.',
      )
    }

    let multiSection = false
    const suggestions = []

    if (this.props.addresses.length > 0) {
      multiSection = true

      const addressesAsSuggestions = this.props.addresses.map(
        (address, idx) => ({
          type: 'address',
          value: address.streetAddress,
          address: {
            ...address,
            // Let's suppose saved addresses are precise
            isPrecise: true,
            needsGeocoding: false,
          },
          index: idx,
        }),
      )

      suggestions.push({
        title: this.props.t('SAVED_ADDRESSES'),
        suggestions: addressesAsSuggestions.slice(0, 5),
      })
    }

    return {
      value: _.isObject(this.props.address)
        ? this.props.address.streetAddress || ''
        : _.isString(this.props.address)
          ? this.props.address
          : '',
      suggestions,
      multiSection,
      loading: false,
      showMapPicker: false,
    }
  },
  onSuggestionsFetchRequested: function() {
    this.setState({
      suggestions: [],
    })
  },
  onSuggestionSelected: function(event, { suggestion }) {
    if (suggestion.type === 'prediction') {
      let address = this.transformSuggestion(suggestion)

      // If the component was configured for,
      // report validity if the address is not precise enough
      if (
        this.props.reportValidity &&
        this.props.preciseOnly &&
        !address.isPrecise &&
        !address.needsGeocoding
      ) {
        this.autosuggest.input.setCustomValidity(
          this.props.t('CART_ADDRESS_NOT_ENOUGH_PRECISION'),
        )
        if (HTMLInputElement.prototype.reportValidity) {
          this.autosuggest.input.reportValidity()
        }
      }

      if (address.isPrecise && address.needsGeocoding) {
        this.setState({ loading: true })

        axios
          .get(
            `/search/geocode?address=${encodeURIComponent(address.streetAddress)}`,
          )
          .then(geocoded => {
            this.setState({ loading: false })
            address = {
              ...address,
              ...geocoded.data,
              geo: geocoded.data,
              geohash: ngeohash.encode(
                geocoded.data.latitude,
                geocoded.data.longitude,
                11,
              ),
              isPrecise: true,
              needsGeocoding: false,
            }
            this.props.onAddressSelected(
              this.state.value,
              address,
              suggestion.type,
            )
          })
          .catch(() => {
            this.setState({ loading: false })
            this.props.onAddressSelected(
              this.state.value,
              address,
              suggestion.type,
            )
          })
      } else {
        address = {
          ...address,
          geohash: ngeohash.encode(
            address.geo.latitude,
            address.geo.longitude,
            11,
          ),
        }
        this.props.onAddressSelected(this.state.value, address, suggestion.type)
      }
    }

    if (suggestion.type === 'address') {
      const geohash = ngeohash.encode(
        suggestion.address.geo.latitude,
        suggestion.address.geo.longitude,
        11,
      )

      const address = {
        ...suggestion.address,
        geohash,
      }

      this.props.onAddressSelected(suggestion.value, address, suggestion.type)
    }

    if (suggestion.type === 'restaurant') {
      window.location.href = window.Routing.generate('restaurant', {
        id: suggestion.restaurant.id,
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
    return <span></span>
  },
  highlightFirstSuggestion: function() {
    return false
  },
  useCache: function() {
    return false
  },
  configure: function() { },
}

const localize = (func, adapter, thisArg) => {
  if (
    Object.prototype.hasOwnProperty.call(localized, thisArg.country) &&
    Object.prototype.hasOwnProperty.call(localized[thisArg.country], func)
  ) {
    return localized[thisArg.country][func].bind(thisArg)
  }

  if (
    Object.prototype.hasOwnProperty.call(adapters, adapter) &&
    Object.prototype.hasOwnProperty.call(adapters[adapter], func)
  ) {
    return adapters[adapter][func].bind(thisArg)
  }

  return generic[func].bind(thisArg)
}

const getSuggestionValue = suggestion => suggestion.value

const renderSuggestion = suggestion => {
  if (suggestion.type === 'plus_code') {
    return (
      <div className="tw:flex tw:items-center tw:gap-1">
        {/* https://lucide.dev/icons/map-pin-plus */}
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="lucide lucide-map-pin-plus-icon lucide-map-pin-plus tw:w-4">
          <path d="M19.914 11.105A7.298 7.298 0 0 0 20 10a8 8 0 0 0-16 0c0 4.993 5.539 10.193 7.399 11.799a1 1 0 0 0 1.202 0 32 32 0 0 0 .824-.738" />
          <circle cx="12" cy="10" r="3" />
          <path d="M16 18h6" />
          <path d="M19 15v6" />
        </svg>
        {suggestion.value}
      </div>
    )
  }
  const parts = [suggestion.value]

  if (suggestion.type === 'address') {
    if (!_.isEmpty(suggestion.address.name)) {
      parts.unshift(suggestion.address.name)
    }
  }

  return <div>{parts.join(' — ')}</div>
}

// https://github.com/moroshko/react-autosuggest#should-render-suggestions-prop
function shouldRenderSuggestions(value) {
  // This allows rendering suggestions for saved adresses
  // when the user just focuses the input without typing anything
  if (value.trimStart().length === 0 && this.state.multiSection) {
    return true
  }

  return value.trimStart().length > 3 || value.trimStart().endsWith(' ')
}

const renderSectionTitle = section => <strong>{section.title}</strong>

const getSectionSuggestions = section => section.suggestions

const SuggestionsContainer = ({
  containerProps,
  children,
  poweredBy,
  onMapPickerLabelClick,
  mapPickerEnabled
}) => {
  const { t } = useTranslation()

  return (
    <div {...containerProps}>
      {children}
      {Array.isArray(children) && (
      <div
        className={clsx(
          'tw:flex',
          'tw:items-center',
          'tw:gap-2',
          'tw:p-2',
          !mapPickerEnabled && 'tw:justify-end',
          mapPickerEnabled && 'tw:justify-between',
        )}>
        {mapPickerEnabled && (
          <button
            type="button"
            // className="tw:btn tw:btn-link tw:btn-xs"
            className="tw:text-xs"
            onClick={() => {
              onMapPickerLabelClick()
            }}>
            <i
              className="fa fa-question-circle"
              aria-hidden="true"></i>
            {t('ADDRESS_AUTOSUGGEST_MAP_PICKER_LABEL')}
          </button>
        )}
        <div>{poweredBy}</div>
      </div>
      )}
    </div>
  )
}

class AddressAutosuggest extends Component {
  constructor(props) {
    super(props)

    this.country = props.country || getCountry() || 'en'
    this.language = props.language || localeDetector()

    const adapter = getAdapter(props, document)
    const adapterOptions = getAdapterOptions(props, document)

    const configure = localize('configure', adapter, this)
    configure(adapterOptions[adapter])

    const onSuggestionsFetchRequestedBase = localize(
      'onSuggestionsFetchRequested',
      adapter,
      this,
    )

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
      400,
    )
    this.onSuggestionsFetchRequestedDebounced = debounce(
      onSuggestionsFetchRequestedCached,
      400,
    )

    this.onSuggestionsFetchRequested = ({ value }) => {
      // We still need to check if text is not empty here,
      // because shouldRenderSuggestions() may return true even when nothing was typed
      // This happens when there are saved adresses
      if (value.trimStart().length === 0) {
        return
      }

      // Check if the input is a plus code - takes priority over all adapters
      const plusCodeMatch = value.match(/\b[23456789CFGHJMPQRVWX]{2,8}\+[23456789CFGHJMPQRVWX]{2,4}\b/i)
      if (plusCodeMatch) {
        this.setState({
          suggestions: [{
            title: 'Plus code',
            suggestions: [{
              type: 'plus_code',
              value: plusCodeMatch[0].toUpperCase(),
              index: 0,
            }],
          }],
          multiSection: true,
        })
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
    this.transformSuggestion = localize('transformSuggestion', adapter, this)
    this.placeholder = localize('placeholder', adapter, this)
    this.poweredBy = localize('poweredBy', adapter, this)
    this.theme = localize('theme', adapter, this)
    this.highlightFirstSuggestion = localize(
      'highlightFirstSuggestion',
      adapter,
      this,
    ).bind(this)()
    this.useCache = localize('useCache', adapter, this)

    this.getFirstSuggestion = this.getFirstSuggestion.bind(this)
    this.getSuggestionsLength = this.getSuggestionsLength.bind(this)
    this.handleKeyDown = this.handleKeyDown.bind(this)

    this.state = this.getInitialState()


    this.onSuggestionSelected = localize('onSuggestionSelected', adapter, this)
    const originalOnSuggestionSelected = this.onSuggestionSelected.bind(this)
    this.onSuggestionSelected = (event, { suggestion }) => {
      if (suggestion.type === 'plus_code') {
        if (!openLocationCode.isValid(suggestion.value)) {
          // Handle invalid plus code
          if (this.props.reportValidity) {
            this.autosuggest.input.setCustomValidity(
              this.props.t('INVALID_PLUS_CODE'),
            )
            if (HTMLInputElement.prototype.reportValidity) {
              this.autosuggest.input.reportValidity()
            }
          }
          return
        }

        // Decode the plus code to get coordinates
        const [cc_lat, cc_long] = coordinates.value
        const { latitudeCenter: latitude, longitudeCenter: longitude } =
          openLocationCode.decode(
            openLocationCode.recoverNearest(
              suggestion.value, cc_lat, cc_long
            )
          )

        // Create address object with decoded coordinates
        const address = {
          streetAddress: suggestion.value,
          latitude,
          longitude,
          geo: { latitude, longitude },
          geohash: ngeohash.encode(latitude, longitude, 11),
          isPrecise: true,
          needsGeocoding: false,
          provider: 'PLUS_CODE',
        }

        this.props.onAddressSelected(
          this.state.value,
          address,
          suggestion.type,
        )
        return
      }

      // Call the original adapter-specific or generic onSuggestionSelected
      originalOnSuggestionSelected(event, { suggestion })
    }
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
        ...this.props.fuseOptions,
      }
    }

    this.fuse = new Fuse(addresses, fuseOptions)
    this.fuseForRestaurants = new Fuse(this.props.restaurants, {
      ...fuseOptions,
      threshold: 0.2,
      keys: ['name'],
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

    if (this.props.onClear) {
      this.props.onClear()
    }
  }

  onChange(event, { newValue }) {
    this.setState({
      value: newValue,
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
        const restaurantsAsSuggestions = restoResults.map(
          (fuseResult, idx) => ({
            type: 'restaurant',
            value: fuseResult.item.name,
            restaurant: fuseResult.item,
            index: idx,
          }),
        )

        suggestions.push({
          title: this.props.t('RESTAURANTS_AND_STORES'),
          suggestions: restaurantsAsSuggestions,
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
          suggestions: addressesAsSuggestions,
        })
        multiSection = true
      }
    }

    // Cache results
    if (predictionsAsSuggestions.length > 0 && cache) {
      storage.set(
        value,
        predictionsAsSuggestions,
        new Date().getTime() + 5 * 60 * 1000,
      ) // Cache for 5 minutes
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

  componentDidUpdate(prevProps) {
    if (
      (!prevProps.address && this.props.address) ||
      (_.isObject(prevProps.address) &&
        _.isObject(this.props.address) &&
        prevProps.address['@id'] !== this.props.address['@id'])
    ) {
      this.setState({ value: this.props.address.streetAddress })
    }
  }

  onSuggestionsClearRequested() {
    let suggestions = []
    if (this.props.addresses.length > 0 && this.state.multiSection) {
      suggestions = filter(
        this.state.suggestions,
        section => section.title === this.props.t('SAVED_ADDRESSES'),
      )
    }

    this.setState({
      suggestions,
    })
  }

  renderInputComponent(inputProps) {

    return (
      <div className="tw:join tw:w-full">
        <button className={clsx('tw:btn tw:join-item',
          !this.props.error && 'tw:btn-primary',
          this.props.error && 'tw:btn-error')}>
          {/* https://lucide.dev/icons/map-pin */}
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"
            className={clsx('lucide lucide-map-pin-icon lucide-map-pin tw:w-4',
              !this.props.error && 'tw:text-primary-content',
              this.props.error && 'tw:text-error-content',
            )}>
            <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" />
            <circle cx="12" cy="10" r="3" />
          </svg>
        </button>
        <div className={clsx('tw:input tw:join-item tw:w-full', this.props.error && 'tw:input-error')}>
          <input {...inputProps} />
          {this.state.postcode && (
            <div
              className="tw:badge tw:badge-soft tw:badge-primary"
            >
              <span>{this.state.postcode.postcode}</span>
              <button
                onClick={() => this.setState({ value: '', postcode: null })}>
                <i className="fa fa-times-circle"></i>
              </button>
            </div>
          )}
          {this.state.value && (
            <button
              type="button"
              onClick={() => this.onClear()}
              tabIndex="-1">
              <i className="fa fa-times-circle"></i>
            </button>
          )}
        </div>
      </div>
    )
  }

  onSuggestionHighlighted({ suggestion }) {
    // safeguard against an infinite componentDidUpdate loop in AddressAutosuggest when modifying input value then selecting
    // (I didn't get why it was entering in such loop)
    if (this.state?.highlightedSuggestion?.id !== suggestion?.id) {
      this.setState({ highlightedSuggestion: suggestion })
    }
  }

  renderSuggestionsContainer({ containerProps, children }) {

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

      return createPortal(
        <SuggestionsContainer
          containerProps={{
            ...containerProps,
            style,
          }}
          mapPickerEnabled={this.props.mapPickerEnabled}
          onMapPickerLabelClick={() => this.setState({ showMapPicker: true })}
          poweredBy={this.poweredBy()}>
          {children}
        </SuggestionsContainer>,
        document.body,
      )
    }

    return (
      <SuggestionsContainer
        containerProps={containerProps}
        mapPickerEnabled={this.props.mapPickerEnabled}
        onMapPickerLabelClick={() => this.setState({ showMapPicker: true })}
        poweredBy={this.poweredBy()}>
        {children}
      </SuggestionsContainer>
    )
  }

  getSuggestionsLength() {
    const { suggestions, multiSection } = this.state
    if (multiSection) {
      return suggestions.reduce(
        (acc, section) => acc + section['suggestions'].length,
        0,
      )
    } else {
      return suggestions.length
    }
  }

  getFirstSuggestion() {
    const { suggestions, multiSection } = this.state
    let suggestionsValues = []

    if (multiSection) {
      suggestionsValues = suggestions.reduce(
        (acc, section) => acc.concat(section['suggestions']),
        [],
      )
    } else {
      suggestionsValues = suggestions
    }

    return suggestionsValues[0]
  }

  handleKeyDown = event => {
    if (
      (event.key === 'Enter' || event.key === 'Tab') &&
      this.getSuggestionsLength() > 0 &&
      this.state.highlightedSuggestion
    ) {
      const selected = this.state.highlightedSuggestion
      this.onSuggestionSelected({}, { suggestion: selected })
      this.setState({ value: selected.value })
    }
  }

  render() {
    const { value, suggestions, multiSection } = this.state

    const inputProps = {
      placeholder: this.placeholder(),
      value,
      onChange: this.onChange.bind(this),
      type: 'search',
      required: this.props.required,
      disabled: this.props.disabled || this.state.loading,
      onKeyDown: e => this.handleKeyDown(e),
      'data-is-address-picker': true, // used to differentiate from the search input to search in the AddressBook
      // FIXME
      // We may override important props such as value, onChange
      // We need to omit some props
      ...this.props.inputProps,
    }

    let otherProps = {}
    if (Object.prototype.hasOwnProperty.call(this.props, 'id')) {
      otherProps = {
        id: this.props.id,
      }
    }

    return (
      <>
        <Autosuggest
          ref={autosuggest => (this.autosuggest = autosuggest)}
          theme={this.theme(theme)}
          suggestions={suggestions}
          onSuggestionsFetchRequested={this.onSuggestionsFetchRequested}
          onSuggestionsClearRequested={this.onSuggestionsClearRequested.bind(
            this,
          )}
          onSuggestionSelected={this.onSuggestionSelected.bind(this)}
          onSuggestionHighlighted={this.onSuggestionHighlighted.bind(this)}
          getSuggestionValue={getSuggestionValue}
          renderInputComponent={this.renderInputComponent.bind(this)}
          renderSuggestionsContainer={this.renderSuggestionsContainer.bind(
            this,
          )}
          renderSuggestion={renderSuggestion}
          shouldRenderSuggestions={shouldRenderSuggestions.bind(this)}
          renderSectionTitle={renderSectionTitle}
          highlightFirstSuggestion={this.highlightFirstSuggestion}
          getSectionSuggestions={getSectionSuggestions}
          multiSection={multiSection}
          inputProps={inputProps}
          containerProps={this.props.containerProps}
          // Useful for debugging (stays open)
          // alwaysRenderSuggestions={true}
          {...otherProps}
        />
        {this.props.mapPickerEnabled && <MapPicker
          isOpen={this.state.showMapPicker}
          onClose={() => this.setState({ showMapPicker: false })}
          onSelect={address => {
            this.setState({ showMapPicker: false })
            this.props.onAddressSelected('[MAP_PICKER]', address)
          }}
        />}
      </>
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
  onAddressSelected: () => { },
  inputProps: {},
  autofocus: false,
  error: false,
  mapPickerEnabled: false
}

AddressAutosuggest.propTypes = {
  address: PropTypes.oneOfType([PropTypes.object, PropTypes.string]).isRequired,
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
  onClear: PropTypes.func,
  mapPickerEnabled: PropTypes.bool
}

export default withTranslation()(AddressAutosuggest)

export const geocode = text => {
  const adapter = getAdapter({}, document)
  const adapterOptions = getAdapterOptions({}, document)

  const fakeThis = {
    country: getCountry() || 'en',
  }

  const configure = localize('configure', adapter, fakeThis)
  configure(adapterOptions[adapter])

  return new Promise(resolve => {
    localize('geocode', adapter, fakeThis)(
      text,
      getCountry() || 'en',
      localeDetector(),
    ).then(address => {
      if (!address || (address.isPrecise && !address.needsGeocoding)) {
        return resolve(address)
      }

      axios
        .get(
          `/search/geocode?address=${encodeURIComponent(address.streetAddress)}`,
        )
        .then(geocoded => {
          resolve({
            ...address,
            ...geocoded.data,
            geo: geocoded.data,
            geohash: ngeohash.encode(
              geocoded.data.latitude,
              geocoded.data.longitude,
              11,
            ),
            needsGeocoding: false,
          })
        })
        .catch(() => {
          resolve(address)
        })
    })
  })
}
