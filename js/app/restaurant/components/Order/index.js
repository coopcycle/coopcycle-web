import React, { Component } from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import Sticky from 'react-stickynode'
import classNames from 'classnames'

import CartHeading from './CartHeading'
import CartButton from './CartButton'

import { sync } from '../../redux/actions'
import {
  selectIsOrderingAvailable,
  selectHasItems,
} from '../../redux/selectors'
import InvitePeopleToOrderButton from './InvitePeopleToOrderButton'
import FulfillmentDetails from './FulfillmentDetails'
import Cart from './Cart'

class Order extends Component {

  componentDidMount() {
    this.props.sync()
  }

  render() {

    const { isMobileCartVisible } = this.props

    return (
      <Sticky>
        <div className={ classNames({
          'cart-wrapper': true,
          'cart-wrapper--show': isMobileCartVisible,
        }) }>

          <div className={ classNames({
            'panel': true,
            'panel-default': true,
            'panel-heading-wrapper': true,
          }) }>
            <CartHeading />
          </div>

          <FulfillmentDetails />

          <div className={ classNames({
            'panel': true,
            'panel-default': true,
          }) }>
            <div className="panel-body">
              <Cart />
              { this.props.isOrderingAvailable &&
                <>
                  <hr />
                  <CartButton />
                  { (this.props.isGroupOrdersEnabled &&
                      this.props.hasItems && !this.props.isPlayer &&
                      window._auth.isAuth) &&
                    <InvitePeopleToOrderButton /> }
                </>
              }
            </div>
          </div>
        </div>
      </Sticky>
    )
  }
}

function mapStateToProps(state) {

  return {
    isMobileCartVisible: state.isMobileCartVisible,
    isOrderingAvailable: selectIsOrderingAvailable(state) && !state.isPlayer,
    hasItems: selectHasItems(state),
    isPlayer: state.isPlayer,
    player: state.player,
    isGroupOrdersEnabled: state.isGroupOrdersEnabled,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    sync: () => dispatch(sync()),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(
  withTranslation()(Order))
