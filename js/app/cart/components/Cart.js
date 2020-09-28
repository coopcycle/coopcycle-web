import React, { Component } from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import Sticky from 'react-stickynode'
import classNames from 'classnames'

import AddressModal from './AddressModal'
import DateModal from './DateModal'
import RestaurantModal from './RestaurantModal'
import AddressAutosuggest from '../../components/AddressAutosuggest'
import CartItems from './CartItems'
import CartHeading from './CartHeading'
import CartTotal from './CartTotal'
import CartButton from './CartButton'
import Time from './Time'
import Takeaway from './Takeaway'

import { changeAddress, sync, disableTakeaway, enableTakeaway } from '../redux/actions'
import { selectIsDeliveryEnabled, selectIsCollectionEnabled } from '../redux/selectors'

class Cart extends Component {

  componentDidMount() {
    this.props.sync()
  }

  render() {

    const { isMobileCartVisible } = this.props

    return (
      <Sticky>
        <div className={ classNames({
          'panel': true,
          'panel-default': true,
          'cart-wrapper': true,
          'cart-wrapper--show': isMobileCartVisible }) }>
          <CartHeading />
          <div className="panel-body">
            <div className="cart">
              <div>
                <AddressAutosuggest
                  addresses={ this.props.addresses }
                  address={ this.props.shippingAddress }
                  geohash={ '' }
                  key={ this.props.streetAddress }
                  onAddressSelected={ (value, address) => this.props.changeAddress(address) }
                  disabled={ this.props.isCollectionOnly || this.props.takeaway }
                  required />
                { this.props.isCollectionEnabled && (
                <div className="text-center">
                  <Takeaway
                    defaultChecked={ this.props.isCollectionOnly }
                    checked={ this.props.takeaway || this.props.isCollectionOnly }
                    onChange={ enabled => enabled ? this.props.enableTakeaway() : this.props.disableTakeaway() }
                    disabled={ this.props.loading || this.props.isCollectionOnly } />
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
    isDeliveryEnabled: selectIsDeliveryEnabled(state),
    isCollectionEnabled: selectIsCollectionEnabled(state),
    isCollectionOnly: (selectIsCollectionEnabled(state) && !selectIsDeliveryEnabled(state)),
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
