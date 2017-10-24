import React from 'react';
import DatePicker from "../cart/DatePicker.jsx";
import PlacesAutocomplete, { geocodeByPlaceId } from 'react-places-autocomplete';


const autocompleteOptions = {
  types: ['address'],
  componentRestrictions: {
    country: "fr"
  }
}

const autocompleteStyles = {
  autocompleteContainer: {
    zIndex: 1
  }
}

const autocompleteClasses = {
  root: 'form-group input-location',
  input: 'form-control',
}

class RestaurantListFilter extends React.Component {

  constructor(props) {
    super(props);
    this.geohashLib = require('ngeohash');
    this.state = {
      nowOrLater: this.props.initialDate ? 'later' : 'now',
      // used to store a valid address, so we use it to refill the form when losing focus
      initialAddress: this.props.address,
      address: this.props.address,
      geohash: this.props.geohash
    }
  }

  onDateTimeChange (ev) {
    this.setState({nowOrLater: ev.target.value})
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

    geocodeByPlaceId(placeId).then(
      (results) => {
        // should always be the case, assert ?
        if (results.length === 1) {
          let place = results[0],
              lat = place.geometry.location.lat(),
              lng = place.geometry.location.lng(),
              geohash = this.geohashLib.encode(lat, lng, 11);
          this.setState({ geohash, address, initialAddress: address});
        }
      }
    );
  }

  shouldComponentUpdate (nextProps, nextState) {

    // handle the switch from 'later' to 'as soon as possible'
    if (this.state.nowOrLater !== nextState.nowOrLater && nextState.nowOrLater === 'now') {
      this.props.onDatePickerChange('');
    }
    else if (this.state.geohash !== nextState.geohash) { // handle geohash change
      this.props.onPlaceChange(nextState.geohash, nextState.address);
    }

    return true;
  }

  render () {
    // datetime picker
    let selectElement = '',
        { nowOrLater, address } = this.state,
        wantsNow =  nowOrLater === 'now';

    // address picker
    const inputProps = {
      value: address,
      onChange: (value) => { this.onAddressChange(value) },
      onBlur: () => { this.onAddressBlur() },
      placeholder: 'Entrez votre adresse'
    }

    if (!wantsNow) {
      selectElement = <DatePicker
                        setDeliveryDate={ this.props.onDatePickerChange }
                        availabilities={ this.props.availabilities }
                        deliveryDate={ this.props.initialDate }
                      />
    }

    return (
      <div className="row">
        <div className="col-md-4">
          <PlacesAutocomplete
            classNames={ autocompleteClasses }
            inputProps={ inputProps }
            options={ autocompleteOptions }
            styles={ autocompleteStyles }
            onSelect={ (address, placeId) => { this.onAddressSelect(address, placeId) }}
          />
        </div>
        <div className="col-md-1">
          <span className="input-height">Quand?</span>
        </div>
        <div className="col-md-3" >
          <select id="basic-url" value={ this.state.nowOrLater } className="form-control" onChange={ (evt) => this.onDateTimeChange(evt) }>
            <option value="now">Au plus tôt</option>
            <option value="later">Plus tard</option>
          </select>
        </div>
        <div className="col-md-4" >
          { selectElement }
        </div>
      </div>
    )
  }
}

export default RestaurantListFilter;