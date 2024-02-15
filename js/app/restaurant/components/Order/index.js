import React, {Component} from 'react'
import {connect} from 'react-redux'
import {withTranslation} from 'react-i18next'
import Sticky from 'react-stickynode'
import classNames from 'classnames'
import { Switch } from 'antd'

import CartItems from './CartItems'
import CartHeading from './CartHeading'
import CartTotal from './CartTotal'
import CartButton from './CartButton'

import {sync, toggleReusablePackaging} from '../../redux/actions'
import {
  selectIsOrderingAvailable,
  selectItems,
  selectReusablePackagingFeatureEnabled,
  selectReusablePackagingEnabled,
} from '../../redux/selectors'
import InvitePeopleToOrderButton from './InvitePeopleToOrderButton'
import FulfillmentDetails from './FulfillmentDetails'

class Cart extends Component {

  componentDidMount() {
    this.props.sync()
  }

  render() {

    const { isMobileCartVisible } = this.props

    return (
      <Sticky>
        <div className={ classNames({
          'cart-wrapper': true,
          'cart-wrapper--show': isMobileCartVisible }) }>

          <div className={classNames({
            'panel': true,
            'panel-default': true,
          })}>
            <CartHeading/>
          </div>

          <FulfillmentDetails />

          <div className={classNames({
            'panel': true,
            'panel-default': true,
          })}>
            <div className="panel-body">
              <div className="cart">
                <CartItems/>
                <div>
                  {(this.props.reusablePackagingFeatureEnabled &&
                      this.props.hasItems) &&
                    <div className="d-flex align-items-center mb-2">
                    <Switch size="small" checked={ this.props.reusablePackagingEnabled } onChange={ (checked) => {
                      this.props.toggleReusablePackaging(checked)
                    } } />
                    <span className="ml-2">{ this.props.t('CART_ENABLE_ZERO_WASTE') }</span>
                  </div>
                  }
                  <CartTotal />
                  { this.props.isOrderingAvailable &&
                  <>
                  <hr />
                  <CartButton />
                  { (this.props.isGroupOrdersEnabled && this.props.hasItems && !this.props.isPlayer && window._auth.isAuth) &&
                  <InvitePeopleToOrderButton /> }
                  </>
                  }
                </div>
              </div>
            </div>
          </div>
        </div>
      </Sticky>
    )
  }
}

function mapStateToProps(state) {

  const items = selectItems(state)

  return {
    isMobileCartVisible: state.isMobileCartVisible,
    isOrderingAvailable: selectIsOrderingAvailable(state) && !state.isPlayer,
    hasItems: !!items.length,
    isPlayer: state.isPlayer,
    player: state.player,
    isGroupOrdersEnabled: state.isGroupOrdersEnabled,
    reusablePackagingFeatureEnabled: selectReusablePackagingFeatureEnabled(state),
    reusablePackagingEnabled: selectReusablePackagingEnabled(state),
  }
}

function mapDispatchToProps(dispatch) {

  return {
    sync: () => dispatch(sync()),
    toggleReusablePackaging: (checked) => dispatch(toggleReusablePackaging(checked)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(Cart))
