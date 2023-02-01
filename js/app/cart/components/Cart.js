import React, { Component } from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import Sticky from 'react-stickynode'
import classNames from 'classnames'

import AddressModal from './AddressModal'
import DateModal from './DateModal'
import RestaurantModal from './RestaurantModal'
import CartItems from './CartItems'
import CartHeading from './CartHeading'
import CartTotal from './CartTotal'
import CartButton from './CartButton'
import Time from './Time'
import FulfillmentMethod from './FulfillmentMethod'
import ProductOptionsModal from './ProductOptionsModal'
import ProductDetailsModal from './ProductDetailsModal'

import { changeAddress, sync, disableTakeaway, enableTakeaway, openAddressModal } from '../redux/actions'
import { selectIsDeliveryEnabled, selectIsCollectionEnabled, selectIsOrderingAvailable, selectItems } from '../redux/selectors'
import InvitePeopleToOrderButton from './InvitePeopleToOrderButton'
import InvitePeopleToOrderModal from './InvitePeopleToOrderModal'

class Cart extends Component {

  componentDidMount() {
    this.props.sync()
  }

  render() {

    const { isMobileCartVisible } = this.props
    const fulfillmentMethod = (this.props.takeaway || this.props.isCollectionOnly) ? 'collection' : 'delivery'

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
                <FulfillmentMethod
                  value={ fulfillmentMethod }
                  shippingAddress={ this.props.shippingAddress }
                  onClick={ () => this.props.openAddressModal(this.props.restaurant) } />
                { this.props.isOrderingAvailable && <Time /> }
              </div>
              <CartItems />
              <div>
                <CartTotal />
                { this.props.isOrderingAvailable && <hr /> }
                { this.props.isOrderingAvailable && <CartButton /> }
                { this.props.isOrderingAvailable && this.props.hasItems && <InvitePeopleToOrderButton /> }
              </div>
            </div>
          </div>
        </div>
        <AddressModal />
        <RestaurantModal />
        <DateModal />
        <ProductOptionsModal />
        <ProductDetailsModal />
        <InvitePeopleToOrderModal />
      </Sticky>
    )
  }
}

function mapStateToProps(state) {

  const items = selectItems(state)

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
    isOrderingAvailable: selectIsOrderingAvailable(state),
    restaurant: state.cart.restaurant,
    hasItems: !!items.length
  }
}

function mapDispatchToProps(dispatch) {

  return {
    changeAddress: address => dispatch(changeAddress(address)),
    sync: () => dispatch(sync()),
    enableTakeaway: () => dispatch(enableTakeaway()),
    disableTakeaway: () => dispatch(disableTakeaway()),
    openAddressModal: (restaurant) => dispatch(openAddressModal({restaurant})),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(Cart))
