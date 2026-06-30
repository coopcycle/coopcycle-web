import React from 'react'
import { useDispatch, useSelector } from 'react-redux'
import clsx from 'clsx'

import { toggleMobileCart } from '../../redux/actions'
import {
  selectIsMobileCartVisible,
  selectSortedErrorMessages,
} from '../../redux/selectors'
import OrderState from './OrderState'
import { useTranslation } from 'react-i18next'

export default function MobileOrderHeading() {
  const isMobileCartVisible = useSelector(selectIsMobileCartVisible)
  const errors = useSelector(selectSortedErrorMessages)

  const dispatch = useDispatch()

  const { t } = useTranslation()

  const handleClick = () => {
    // use similar to ReactModal approach to prevent body in the background from scrolling
    if (isMobileCartVisible) {
      document.body.classList.remove('body--no-scroll')
    } else {
      document.body.classList.add('body--no-scroll')
    }

    dispatch(toggleMobileCart())
  }

  return (
    <div
      className="cursor-pointer">
      <div
        className={clsx('flex items-center px-2 h-(--heading-height)', {
          'bg-primary text-primary-content': errors.length === 0,
          'bg-warning text-warning-content': errors.length > 0,
        }) }
        onClick={ handleClick }>
        <div className="flex flex-1 items-center justify-center">
          { isMobileCartVisible ? (<span>{t('CART_TITLE')}</span>) : (<OrderState />) }
        </div>
        <span className="justify-self-end">
          <i className={ isMobileCartVisible
            ? 'fa fa-chevron-up'
            : 'fa fa-chevron-down' } />
        </span>
      </div>
    </div>
  )
}
