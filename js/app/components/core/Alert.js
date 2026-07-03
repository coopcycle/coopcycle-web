import React from 'react'
import clsx from 'clsx'
import Spinner from './Spinner'

// Bootstrap-based alert
export default ({
  children,
  loading,
  icon,
  success,
  info,
  warning,
  danger,
  noBottomMargin,
}) => {
  return (
    <div
      className={clsx('alert d-flex align-items-center', {
        'alert-success': success,
        'alert-info': info,
        'alert-warning': warning,
        'alert-danger': danger,
        'mb-0': noBottomMargin,
      })}>
      {loading ? (
        <span>
          <Spinner />  
        </span>
      ) : null}
      {icon ? (
        <span>
          <i className={['fa', `fa-${icon}`].join(' ')} aria-hidden="true"></i> 
        </span>
      ) : null}
      {children}
    </div>
  )
}
