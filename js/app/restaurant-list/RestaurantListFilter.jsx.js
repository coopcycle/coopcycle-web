import React from 'react';
import DatePicker from "../cart/DatePicker.jsx";

class RestaurantListFilter extends React.Component {

  constructor(props) {
    super(props);
    this.state = {
      value: this.props.initialDate ? 'later' : 'now'
    }
  }

  onChange (ev) {
    this.setState({'value': ev.target.value})
  }

  shouldComponentUpdate (nextProps, nextState) {
    if (this.state.value !== nextState.value && nextState.value === 'now') {
      this.props.onDatePickerChange('');
    }

    return true;
  }

  render () {
    let selectElement = '',
        { value } = this.state,
        wantsNow =  value === 'now';

    if (!wantsNow) {
      selectElement = <DatePicker
                        setDeliveryDate={ this.props.onDatePickerChange }
                        availabilities={ this.props.availabilities }
                        deliveryDate={ this.props.initialDate }
                      />
    }

    return (
      <div className="row">
          <div className="col-md-4" >
            <select value={ this.state.value } className="form-control" onChange={ (evt) => this.onChange(evt) }>
              <option value="now">Le plus tôt possible</option>
              <option value="later">Plus tard</option>
            </select>
          </div>
          <div className="col-md-8" >
            { selectElement }
          </div>
      </div>
    )
  }
}

export default RestaurantListFilter;