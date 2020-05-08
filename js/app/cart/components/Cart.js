import React, { Component } from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import Sticky from 'react-stickynode'

import AddressModal from './AddressModal'
import DateModal from './DateModal'
import RestaurantModal from './RestaurantModal'
import AddressAutosuggest from '../../components/AddressAutosuggest'
import CartErrors from './CartErrors'
import CartItems from './CartItems'
import CartHeading from './CartHeading'
import CartTotal from './CartTotal'
import CartButton from './CartButton'
import Time from './Time'
import Takeaway from './Takeaway'

import { changeAddress, sync, disableTakeaway, enableTakeaway } from '../redux/actions'

let isXsDevice = $('.visible-xs').is(':visible')

class Cart extends Component {

  componentDidMount() {
    this.props.sync()
  }

  render() {

    const { isMobileCartVisible } = this.props

    const panelClasses = ['panel', 'panel-default', 'cart-wrapper']
    if (isMobileCartVisible) {
      panelClasses.push('cart-wrapper--show')
    }

    return (
      <Sticky enabled={ !isXsDevice }>
        <div className={ panelClasses.join(' ') }>
          <CartHeading />
          <div className="panel-body">
            <CartErrors />
            <div className="cart">
              <div>
                { !this.props.takeaway && (
                <AddressAutosuggest
                  addresses={ this.props.addresses }
                  address={ this.props.shippingAddress }
                  geohash={ '' }
                  key={ this.props.streetAddress }
                  onAddressSelected={ (value, address) => this.props.changeAddress(address) } />
                )}
                { this.props.takeawayEnabled && (
                <div className="text-center">
                  <Takeaway
                    checked={ this.props.takeaway }
                    onChange={ enabled => enabled ? this.props.enableTakeaway() : this.props.disableTakeaway() }
                    disabled={ this.props.loading } />
                </div>
                )}
                <Time />
              </div>
              <CartItems />
              <div>
                <CartTotal />
                <CartButton />
              </div>
            </div>
          </div>
        </div>
        <AddressModal />
        <RestaurantModal />
        <DateModal />
      </Sticky>
    )
  }
}

function mapStateToProps(state) {

  return {
    shippingAddress: state.cart.shippingAddress,
    streetAddress: state.cart.shippingAddress ? state.cart.shippingAddress.streetAddress : '',
    isMobileCartVisible: state.isMobileCartVisible,
    addresses: state.addresses,
    takeawayEnabled: state.cart.restaurant.takeawayEnabled,
    takeaway: state.cart.takeaway,
    loading: state.isFetching,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    changeAddress: address => dispatch(changeAddress(address)),
    sync: () => dispatch(sync()),
    enableTakeaway: () => dispatch(enableTakeaway()),
    disableTakeaway: () => dispatch(disableTakeaway()),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(Cart))
