import React, { useEffect, useState } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import Sticky from 'react-stickynode'
import classNames from 'classnames'

import { sync } from '../../redux/actions'
import {
  selectIsMobileCartVisible,
} from '../../redux/selectors'
import FulfillmentDetails from './FulfillmentDetails'
import MobileOrderHeading from './MobileOrderHeading'
import CartWrapper from './CartWrapper'

// mobile (and small tablets)
export function OrderOverlay() {
  const isMobileCartVisible = useSelector(selectIsMobileCartVisible)

  const dispatch = useDispatch()

  useEffect(() => {
    dispatch(sync())
  }, [])

  return (
    <div className={ classNames('order-overlay', {
      'order-overlay--show': isMobileCartVisible,
    }) }>

      <MobileOrderHeading />

      <div className="order-overlay__content">
        <FulfillmentDetails />

        <CartWrapper />
      </div>
    </div>
  )
}


// desktop (and larger tablets)
export function StickyOrder() {
  const [ menuNavHeight, setMenuNavHeight ] = useState(0)

  const dispatch = useDispatch()

  useEffect(() => {
    dispatch(sync())
  }, [])

  const el = document.getElementById('restaurant-menu-nav')

  useEffect(() => {
    if (el) {
      const height = el.clientHeight

      document.documentElement.style.setProperty('--restaurant-menu-nav-height',
        `${ height }px`)
      setMenuNavHeight(height)
    }
  }, [ el ])

  return (
    <Sticky top={ menuNavHeight } bottomBoundary=".content">
      <CartWrapper />
    </Sticky>)
}
