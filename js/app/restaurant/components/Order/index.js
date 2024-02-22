import React, { useEffect, useState } from 'react'
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
  selectCartItemsRelatedErrorMessages,
  selectIsFetching,
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
  const isFetching = useSelector(selectIsFetching)
  const hasItems = useSelector(selectHasItems)
  const cartItemsRelatedErrors = useSelector(selectCartItemsRelatedErrorMessages)
  const reusablePackagingFeatureEnabled = useSelector(selectReusablePackagingFeatureEnabled)

  const [menuNavHeight, setMenuNavHeight] = useState(0)

  const dispatch = useDispatch()

  useEffect(() => {
    dispatch(sync())
  }, [])

  useEffect(() => {
    const height = document.getElementById('restaurant-menu-nav').clientHeight
    setMenuNavHeight(height)
  })

  return (<Sticky top={menuNavHeight}>
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
          { (hasItems || isFetching) ? (<div className="cart__footer">
            { reusablePackagingFeatureEnabled ? (
              <ReusablePackagingSwitch />) : null }
            <CartTotal />
            <div className="mt-4 d-flex align-items-center justify-content-center flex-wrap">
              <InvitePeopleToOrderButton />
              <CartButton />
            </div>
          </div>) : (<div><InvitePeopleToOrderButton /></div>) }
        </div>
      </div>
    </div>
  </Sticky>)
}
