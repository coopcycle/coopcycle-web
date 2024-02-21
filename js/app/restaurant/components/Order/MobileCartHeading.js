import React from 'react'
import { useDispatch, useSelector } from 'react-redux'
import classNames from 'classnames'

import { toggleMobileCart } from '../../redux/actions'
import {
  selectErrorMessages,
  selectWarningMessages,
  selectIsMobileCartVisible,
} from '../../redux/selectors'
import OrderState from './OrderState'

export default function MobileCartHeading() {
  const isMobileCartVisible = useSelector(selectIsMobileCartVisible)
  const errors = useSelector(selectErrorMessages)
  const warnings = useSelector(selectWarningMessages)

  const dispatch = useDispatch()

  const hasErrorsOrWarnings = errors.length > 0 || warnings.length > 0

  return (
    <div
      className={ classNames({
        'mobile-cart': true,
        'panel': true,
        'panel-default': true,
      }) }>
      <div
        className={ classNames({
          'panel-heading': true,
          'panel-heading--warning': hasErrorsOrWarnings,
        }) }
        onClick={ () => dispatch(toggleMobileCart()) }>
        <div className="panel-heading__body">
          { isMobileCartVisible ? null : (<OrderState />) }
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
