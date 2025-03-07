import React from 'react'
import classNames from 'classnames'
import _ from 'lodash'
import { useDispatch, useSelector } from 'react-redux'
import { useTranslation } from 'react-i18next'
import { Switch } from 'antd'

import {
  selectCartItemsRelatedErrorMessages,
  selectHasItems,
  selectIsFetching,
  selectReusablePackagingEnabled,
  selectReusablePackagingFeatureEnabled,
} from '../../redux/selectors'
import { toggleReusablePackaging } from '../../redux/actions'
import Cart from './Cart'
import CartTotal from './CartTotal'
import InvitePeopleToOrderButton from './InvitePeopleToOrderButton'
import CartButton from './CartButton'

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

export default function CartWrapper() {
  const isFetching = useSelector(selectIsFetching)
  const hasItems = useSelector(selectHasItems)
  const cartItemsRelatedErrors = useSelector(
    selectCartItemsRelatedErrorMessages)
  const reusablePackagingFeatureEnabled = useSelector(
    selectReusablePackagingFeatureEnabled)

  return (
    <div className={ classNames(
      'panel',
      'panel-default',
      'panel-cart-wrapper') }>
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
          <div
            className="mt-4 d-flex align-items-center justify-content-center flex-wrap">
            <InvitePeopleToOrderButton />
            <CartButton />
          </div>
        </div>) : (<div><InvitePeopleToOrderButton /></div>) }
      </div>
    </div>
  )
}
