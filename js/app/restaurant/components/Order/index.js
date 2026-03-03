import React, { useEffect, useState } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import classNames from 'classnames'

import { sync } from '../../redux/actions'
import { selectIsMobileCartVisible } from '../../redux/selectors'
import FulfillmentDetails from './FulfillmentDetails'
import MobileOrderHeading from './MobileOrderHeading'
import CartWrapper from './CartWrapper'
import { createPortal } from 'react-dom'

// mobile (and small tablets)
function OrderOverlay() {
  const isMobileCartVisible = useSelector(selectIsMobileCartVisible)

  return (
    <div
      className={classNames('order-overlay', 'flex', 'lg:hidden', {
        'order-overlay--show': isMobileCartVisible,
      })}>
      <MobileOrderHeading />

      <div className="order-overlay__content">
        <FulfillmentDetails />
        <CartWrapper />
      </div>
    </div>
  )
}

// desktop (and larger tablets)
function StickyOrder() {

  return (
    <div className="hidden lg:block lg:w-full">
      <div className="card bg-base-100 shadow-sm">
        <div className="card-body">
          <CartWrapper />
        </div>
      </div>
    </div>
  )
}

// desktop only
const fulfilmentDetailsContainer = document.getElementById(
  'restaurant__fulfilment-details__container',
)

export function OrderLayout() {
  const dispatch = useDispatch()

  useEffect(() => {
    dispatch(sync())
  }, [])

  return (
    <>
      {createPortal(<FulfillmentDetails />, fulfilmentDetailsContainer)}
      <StickyOrder />
      <OrderOverlay />
    </>
  )
}
