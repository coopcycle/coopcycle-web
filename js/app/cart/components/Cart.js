import React, { Component } from 'react'
import { connect } from 'react-redux'
import { translate } from 'react-i18next'
import Sticky from 'react-stickynode'
import _ from 'lodash'
import md5 from 'locutus/php/strings/md5'

import AddressModal from './AddressModal'
import RestaurantModal from './RestaurantModal'
import AddressAutosuggest from '../../components/AddressAutosuggest'
import CartErrors from './CartErrors'
import CartItems from './CartItems'
import CartHeading from './CartHeading'
import CartTotal from './CartTotal'
import CartButton from './CartButton'
import DatePicker from './DatePicker'

import { changeAddress, changeDate, sync, geocodeAndSync } from '../redux/actions'

let isXsDevice = $('.visible-xs').is(':visible')

class Cart extends Component {

  componentDidMount() {

    const { streetAddress, shippingAddress } = this.props

    if (streetAddress && shippingAddress && !Array.isArray(shippingAddress.latlng)) {
      this.props.geocodeAndSync()
    } else {
      this.props.sync()
    }
  }

  render() {

    const { items, isMobileCartVisible } = this.props

    const panelClasses = ['panel', 'panel-default', 'cart-wrapper']
    if (isMobileCartVisible) {
      panelClasses.push('cart-wrapper--show')
    }

    return (
      <Sticky enabled={ !isXsDevice } top={ 30 }>
        <div className={ panelClasses.join(' ') }>
          <CartHeading />
          <div className="panel-body">
            <CartErrors />
            <div className="cart">
              <AddressAutosuggest
                addresses={ this.props.addresses }
                address={ this.props.streetAddress }
                geohash={ '' }
                key={ this.props.streetAddress }
                onAddressSelected={ (value, address, type) => this.props.changeAddress(address) } />
              <hr />
              <DatePicker
                dateInputName={ this.props.datePickerDateInputName }
                timeInputName={ this.props.datePickerTimeInputName }
                availabilities={ this.props.availabilities }
                value={ _.first(this.props.availabilities) }
                key={ md5(this.props.availabilities.join('|')) }
                onChange={ (dateString) => this.props.changeDate(dateString) } />
              <hr />
              <CartItems />
              <hr />
              <CartTotal />
              <CartButton />
            </div>
          </div>
        </div>
        <AddressModal />
        <RestaurantModal />
      </Sticky>
    )
  }
}

function mapStateToProps(state) {

  const { cart, restaurant } = state

  let items = cart.items
  if (cart.restaurant.id !== restaurant.id) {
    items = []
  }

  return {
    items,
    availabilities: state.availabilities,
    datePickerDateInputName: state.datePickerDateInputName,
    datePickerTimeInputName: state.datePickerTimeInputName,
    shippingAddress: state.cart.shippingAddress,
    streetAddress: state.cart.shippingAddress ? state.cart.shippingAddress.streetAddress : '',
    isMobileCartVisible: state.isMobileCartVisible,
    addresses: state.addresses,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    changeAddress: address => dispatch(changeAddress(address)),
    changeDate: date => dispatch(changeDate(date)),
    sync: () => dispatch(sync()),
    geocodeAndSync: () => dispatch(geocodeAndSync()),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(translate()(Cart))
