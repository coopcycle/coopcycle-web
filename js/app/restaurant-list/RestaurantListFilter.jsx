import React from 'react';
import DatePicker from "../cart/DatePicker.jsx";
import AddressPicker from "../address/AddressPicker.jsx";


class RestaurantListFilter extends React.Component {

  constructor(props) {
    super(props);
    this.geohashLib = require('ngeohash');
    this.state = {
      nowOrLater: this.props.initialDate ? 'later' : 'now',
    }
  }

  onDateTimeChange (ev) {
    this.setState({nowOrLater: ev.target.value})
  }

  shouldComponentUpdate (nextProps, nextState) {

    // handle the switch from 'later' to 'as soon as possible'
    if (this.state.nowOrLater !== nextState.nowOrLater && nextState.nowOrLater === 'now') {
      this.props.onDatePickerChange('');
    }

    return true;
  }

  render () {
    // datetime picker
    let selectElement = '',
        { nowOrLater } = this.state,
        wantsNow =  nowOrLater === 'now';

    // address picker
    let { onPlaceChange, geohash, address, preferredResults } = this.props

    if (!wantsNow) {
      selectElement = <DatePicker
        onChange={ this.props.onDatePickerChange }
        availabilities={ this.props.availabilities }
        value={ this.props.initialDate }
      />
    }

    return (
      <div className="row">
        <div className="col-md-4">
          <AddressPicker
            geohash = { geohash }
            address = { address }
            onPlaceChange = { onPlaceChange }
            preferredResults = { preferredResults }
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