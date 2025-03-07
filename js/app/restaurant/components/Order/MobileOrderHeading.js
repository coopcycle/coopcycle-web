import React from 'react'
import { useDispatch, useSelector } from 'react-redux'
import classNames from 'classnames'

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
      className={ classNames(
        'order-overlay__heading',
        'panel',
        'panel-default',
      ) }>
      <div
        className={ classNames('panel-heading', {
          'panel-heading--warning': errors.length > 0,
        }) }
        onClick={ handleClick }>
        <div className="panel-heading__body">
          { isMobileCartVisible ? (<span>{t('CART_TITLE')}</span>) : (<OrderState />) }
        </div>
        <span className="panel-heading__right">
          <i className={ isMobileCartVisible
            ? 'fa fa-chevron-up'
            : 'fa fa-chevron-down' } />
        </span>
      </div>
    </div>
  )
}
