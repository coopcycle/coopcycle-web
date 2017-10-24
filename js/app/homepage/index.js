import React from 'react';
import PlacesAutocomplete, { geocodeByPlaceId, geocodeByAddress } from 'react-places-autocomplete';
import { render } from 'react-dom';

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
  }
}

const autocompleteClasses = {
  root: 'form-group input-location',
  input: 'form-control',
  autocompleteItemActive: 'location-result--active'
}


class HomeSearch extends React.Component {

  constructor(props) {
    super(props);
    this.geohashLib = require('ngeohash');
    this.state = {
      address: '',
      initialAddress: '',
      geohash: ''
    }
    this.insertPreferredResults = this.insertPreferredResults.bind(this);
    this.onAddressSelect = this.onAddressSelect.bind(this);
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

  onAddressChange (value) {
    /*
      Controller for the address input text field
     */
    this.setState({address: value})
  }

  onAddressBlur() {
    this.setState({address: this.state.initialAddress})
  }

  onAddressSelect (address, placeId) {
    /*
      Controller for address selection (i.e. click on address in the dropdown)
     */

    let func
    if (placeId) {
      func = geocodeByPlaceId
    }
    else {
      func = geocodeByAddress
    }

    func(placeId).then(
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

  render () {
    let { address } = this.state;

    const inputProps = {
      value: address,
      onChange: (value) => { this.onAddressChange(value) },
      onBlur: () => { this.onAddressBlur() },
      placeholder: 'Entrez votre adresse'
    }

    const AutocompleteItem = (suggestion) => {
      let classes = ["location-result"]

      if (suggestion.preferred) {
        classes.push('location-result--preferred');
      }

      return (
        <div className={ classes.join(' ') }>
          { suggestion.suggestion }
        </div>)
    }

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
      />
    )
  }
}

function onPlaceChange (geohash, address) {
    localStorage.setItem('search_geohash', geohash);
    localStorage.setItem('search_address', address);
    $('#address-search-form').find('input[name=geohash]').val(geohash);
    $('#address-search-form').submit();
}

let addresses = window.AppData.addresses,
  preferredResults = addresses.map(function (item) { return { suggestion: item.streetAddress, preferred: true }});

render(
  <HomeSearch
    onPlaceChange = { onPlaceChange }
    preferredResults = { preferredResults }
  />,
  document.getElementById('address-search')
);
