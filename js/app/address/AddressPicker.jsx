import React from 'react';
import PlacesAutocomplete, { geocodeByAddress } from 'react-places-autocomplete';
import PropTypes from 'prop-types';

const autocompleteOptions = {
  types: ['address'],
  componentRestrictions: {
    country: "fr"
  }
}

const autocompleteStyles = {
  autocompleteContainer: {
    zIndex: 1
  },
  autocompleteItem: {
    padding: 0
  },
}

const autocompleteClasses = {
  root: 'form-group input-location',
  input: 'form-control',
  autocompleteItemActive: 'location-result--active'
}


class AddressPicker extends React.Component {

  constructor(props) {
    super(props);
    this.geohashLib = require('ngeohash');

    let { geohash, address } = this.props;
    // we use `initialAddress` to fill the form with a valid address on blur
    // `address` is used to control the input field
    this.state = {
      initialAddress: address,
      address: address,
      geohash: geohash
    }

    this.insertPreferredResults = this.insertPreferredResults.bind(this);
    this.onAddressSelect = this.onAddressSelect.bind(this);
    this.onAddressChange = this.onAddressChange.bind(this);
    this.onAddressBlur = this.onAddressBlur.bind(this);
  }

  onAddressChange (value) {
    /*
      Controller for the address input text field
     */
    this.setState({address: value});
  }

  onAddressBlur() {
    this.setState({address: this.state.initialAddress})
  }

  onAddressSelect (address, placeId) {
    /*
      Controller for address selection (i.e. click on address in the dropdown)
     */

    geocodeByAddress(address).then(
      (results) => {
        // should always be the case, assert ?
        if (results.length === 1) {
          let place = results[0],
            lat = place.geometry.location.lat(),
            lng = place.geometry.location.lng(),
            geohash = this.geohashLib.encode(lat, lng, 11);
          this.setState({ geohash, address, initialAddress: address });
        }
      }
    );
  }

  shouldComponentUpdate (nextProps, nextState) {

    if (this.state.geohash !== nextState.geohash) { // handle geohash change
      this.props.onPlaceChange(nextState.geohash, nextState.address);
    }

    return true;
  }

  insertPreferredResults ({results}, callback) {
    return callback(this.props.preferredResults.concat(results))
  }

  render () {
    let { preferredResults, inputProps } = this.props
    let { address } = this.state

    const AutocompleteItem = (suggestion) => {
      let classes = ["location-result"]

      if (suggestion.preferred) {
        classes.push('location-result--preferred')
      }

      return (
        <div className={ classes.join(' ') }>
          { suggestion.suggestion }
        </div>)
    }

    inputProps.value = address
    inputProps.onChange = this.onAddressChange
    inputProps.onBlur = this.onBlur
    inputProps.placeholder = this.placeholder

    return (
      <PlacesAutocomplete
        preferredResults={ preferredResults }
        autocompleteItem={ AutocompleteItem }
        classNames={ autocompleteClasses }
        inputProps={ inputProps }
        options={ autocompleteOptions }
        styles={ autocompleteStyles }
        onSelect={ this.onAddressSelect }
        onSearch={ this.insertPreferredResults }
        // uncomment this if your debugging the style of the suggested addresses
        // alwaysRenderSuggestion
      />
    )
  }
}

AddressPicker.defaultProps = {
  inputProps: {}
}

AddressPicker.propTypes = {
  preferredResults:  PropTypes.arrayOf(
    PropTypes.shape({
      suggestion: PropTypes.string.isRequired,
      preferred: PropTypes.bool.isRequired,
  })).isRequired,
  address: PropTypes.string.isRequired,
  geohash: PropTypes.string.isRequired,
  onPlaceChange: PropTypes.func.isRequired,
  inputProps: PropTypes.object
}

export default AddressPicker