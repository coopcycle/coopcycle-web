import React, {Component} from 'react'
import {connect} from 'react-redux'
import {withTranslation} from 'react-i18next'
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

import {
  changeAddress,
  disableTakeaway,
  enableTakeaway,
  openAddressModal, setPlayer,
  subscribe,
  sync,
  unsubscribe
} from '../redux/actions'
import {
  selectIsCollectionEnabled,
  selectIsDeliveryEnabled,
  selectIsOrderingAvailable,
  selectItems
} from '../redux/selectors'
import InvitePeopleToOrderButton from './InvitePeopleToOrderButton'
import InvitePeopleToOrderModal from './InvitePeopleToOrderModal'
import SetGuestCustomerEmailModal from './SetGuestCustomerEmailModal'

class Cart extends Component {

  componentDidMount() {
    this.props.sync()
    if (this.props.invitation && !this.props.isPlayer) {
      this.props.setPlayer()
    }
  }

  componentDidUpdate(prevProps) {

    if (prevProps.player.token === null && this.props.player.token)  {
      console.log(">> Subscribe at update")
      this.props.subscribe()
    }

    if (this.props.player.token === null && prevProps.player.token)  {
      this.props.unsubscribe()
    }

  }
  componentWillUnmount() {
    this.props.unsubscribe()
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
                { (this.props.isOrderingAvailable && this.props.hasItems && !this.props.isPlayer) && <InvitePeopleToOrderButton /> }
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
        <SetGuestCustomerEmailModal />
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
    hasItems: !!items.length,
    isPlayer: state.isPlayer,
    player: state.player,
    invitation: state.cart.invitation,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    changeAddress: address => dispatch(changeAddress(address)),
    sync: () => dispatch(sync()),
    enableTakeaway: () => dispatch(enableTakeaway()),
    disableTakeaway: () => dispatch(disableTakeaway()),
    openAddressModal: (restaurant) => dispatch(openAddressModal({restaurant})),
    subscribe: () => dispatch(subscribe()),
    unsubscribe: () => dispatch(unsubscribe()),
    setPlayer: (options = {}) => dispatch(setPlayer(options))
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(Cart))
