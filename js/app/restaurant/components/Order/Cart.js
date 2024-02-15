import CartItems from './CartItems'
import { Switch } from 'antd'
import CartTotal from './CartTotal'
import React from 'react'
import { useDispatch, useSelector } from 'react-redux'
import {
  selectHasItems,
  selectReusablePackagingEnabled,
  selectReusablePackagingFeatureEnabled,
} from '../../redux/selectors'
import { toggleReusablePackaging } from '../../redux/actions'
import { useTranslation } from 'react-i18next'

export default function Cart() {
  const reusablePackagingFeatureEnabled = useSelector(
    selectReusablePackagingFeatureEnabled)
  const reusablePackagingEnabled = useSelector(selectReusablePackagingEnabled)

  const hasItems = useSelector(selectHasItems)

  const dispatch = useDispatch()

  const { t } = useTranslation()

  return (
    <div className="cart">
      <CartItems />
      <div>
        { (reusablePackagingFeatureEnabled && hasItems) &&
          <div className="d-flex align-items-center mb-2">
            <Switch size="small"
                    checked={ reusablePackagingEnabled }
                    onChange={ (checked) => {
                      dispatch(toggleReusablePackaging(checked))
                    } } />
            <span className="ml-2">{ t('CART_ENABLE_ZERO_WASTE') }</span>
          </div>
        }
        <CartTotal />
      </div>
    </div>
  )
}
