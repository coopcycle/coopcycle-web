import React, { useEffect } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import { useTranslation } from 'react-i18next'
import Sticky from 'react-stickynode'
import classNames from 'classnames'
import { Switch } from 'antd'

import { sync, toggleReusablePackaging } from '../../redux/actions'
import {
  selectHasItems,
  selectReusablePackagingFeatureEnabled,
  selectReusablePackagingEnabled,
  selectIsMobileCartVisible,
  selectIsPlayer,
  selectIsGroupOrdersEnabled,
  selectCartItemsRelatedErrorMessages,
} from '../../redux/selectors'
import InvitePeopleToOrderButton from './InvitePeopleToOrderButton'
import FulfillmentDetails from './FulfillmentDetails'
import Cart from './Cart'
import CartTotal from './CartTotal'
import MobileCartHeading from './MobileCartHeading'
import CartButton from './CartButton'
import _ from 'lodash'

function ReusablePackagingSwitch() {
  const reusablePackagingEnabled = useSelector(selectReusablePackagingEnabled)

  const { t } = useTranslation()

  const dispatch = useDispatch()

  return (<div className="d-flex align-items-center mb-2">
    <Switch
      size="small"
      checked={ reusablePackagingEnabled }
      onChange={ (checked) => {
        dispatch(toggleReusablePackaging(checked))
      } } />
    <span
      className="ml-2">
        { t('CART_ENABLE_ZERO_WASTE') }
      </span>
  </div>)
}

export default function Order() {
  const isMobileCartVisible = useSelector(selectIsMobileCartVisible)
  const hasItems = useSelector(selectHasItems)
  const cartItemsRelatedErrors = useSelector(selectCartItemsRelatedErrorMessages)
  const isGroupOrdersEnabled = useSelector(selectIsGroupOrdersEnabled)
  const isPlayer = useSelector(selectIsPlayer)
  const reusablePackagingFeatureEnabled = useSelector(selectReusablePackagingFeatureEnabled)
  
  const dispatch = useDispatch()

  useEffect(() => {
    dispatch(sync())
  }, [])

  return (<Sticky>
    <div className={ classNames({
      'order-wrapper': true, 'order-wrapper--show': isMobileCartVisible,
    }) }>

      <MobileCartHeading />

      <FulfillmentDetails />

      <div className={ classNames({
        'panel': true, 'panel-default': true, 'panel-cart-wrapper': true,
      }) }>
        <div className="panel-body">
          { cartItemsRelatedErrors.length > 0 ? (
            <div className="alert alert-warning">
              <i className="fa fa-warning"></i>
              &nbsp;
              <span>{ _.first(cartItemsRelatedErrors) }</span>
            </div>) : null }
          <Cart />
          { hasItems ? (<div className="cart__footer">
            { reusablePackagingFeatureEnabled ? (
              <ReusablePackagingSwitch />) : null }
            <CartTotal />
            <CartButton />
          </div>) : null }
          { (isGroupOrdersEnabled && !isPlayer && window._auth.isAuth) ? (
            <InvitePeopleToOrderButton />) : null }
        </div>
      </div>
    </div>
  </Sticky>)
}
