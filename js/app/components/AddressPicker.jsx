import React from 'react'
import PlacesAutocomplete, { geocodeByAddress } from 'react-places-autocomplete'
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

class AddressPicker extends React.Component {

  constructor(props) {
    super(props)

    let { address } = this.props
    // we use `initialAddress` to fill the form with a valid address on blur
    // `address` is used to control the input field
    this.state = {
      initialAddress: address,
      value: address,
      address: {}
    }

    this.onAddressSelect = this.onAddressSelect.bind(this)
    this.onAddressChange = this.onAddressChange.bind(this)
    this.onAddressBlur = this.onAddressBlur.bind(this)
    this.onAddressKeyUp = this.onAddressKeyUp.bind(this)
    this.onClear = this.onClear.bind(this)
  }

  componentDidMount() {
    if (this.props.autofocus) {
      this.input.focus()
    }
  }

  componentDidUpdate(prevProps, prevState) {
    if (this.state.address !== prevState.address) {
      this.props.onPlaceChange(this.state.value, this.state.address)
    }
  }

  onClear() {
    this.setState({ value: '' })
  }

  onAddressChange(value) {
    this.setState({ value })
  }

  onAddressBlur() {
    this.setState({ value: this.state.initialAddress })
  }

  onAddressKeyUp(evt) {
    if (evt.key == 'Enter') {
      this.props.onPlaceChange(this.state.value, this.state.address)
    }
  }

  setFocus() {
    this.input.focus()
  }

  onAddressSelect(value) {

    this.setState({ value })

    geocodeByAddress(value).then(
      (results) => {
        // should always be the case, assert ?
        if (results.length === 1) {

          const place = results[0],
            lat = place.geometry.location.lat(),
            lng = place.geometry.location.lng(),
            geohash = ngeohash.encode(lat, lng, 11)

          this.setState({
            address: {
              ...placeToAddress(place),
              geohash,
            },
            initialAddress: value
          })
        }
      }
    )
  }

  render () {
    return (
      <div className="autocomplete-wrapper">
        <PlacesAutocomplete
          value={this.state.value}
          onChange={this.onAddressChange}
          onSelect={this.onAddressSelect}
          searchOptions={autocompleteOptions}
          highlightFirstSuggestion={true}>
          {({ getInputProps, suggestions, getSuggestionItemProps }) => (
            <div className="form-group input-location-wrapper">
              <input
                ref={ input => { this.input = input } }
                {...getInputProps({
                  onKeyUp: this.onAddressKeyUp,
                  onBlur: this.onAddressBlur,
                  placeholder: i18n.t('ENTER_YOUR_ADDRESS'),
                  className: 'form-control input-location',
                })}
              />
              { suggestions.length > 0 && (
                <div className="autocomplete-suggestions-wrapper">
                  {suggestions.map(suggestion => {
                    const className = suggestion.active
                      ? 'location-result location-result--active'
                      : 'location-result'
                    return (
                      <div {...getSuggestionItemProps(suggestion, { className })}>
                        <span>{suggestion.description}</span>
                      </div>
                    )
                  })}
                  <div className="autocomplete-footer">
                    <div>
                      <img src={ require('./powered_by_google_on_white.png') } />
                    </div>
                  </div>
                </div>
              )}
            </div>
          )}
        </PlacesAutocomplete>
        { this.state.value && (
          <button className="autocomplete-clear" onClick={this.onClear}>
            <i className="fa fa-times-circle"></i>
          </button>
        )}
      </div>
    )
  }
}

AddressPicker.propTypes = {
  address: PropTypes.string,
  geohash: PropTypes.string.isRequired,
  onPlaceChange: PropTypes.func.isRequired,
}

export default AddressPicker
