import React from 'react'
import classNames from 'classnames'

// Bootstrap-based alert
export default ({ children, icon, success, info, warning, danger }) => {
  return (
    <div
      className={classNames({
        alert: true,
        'alert-success': success,
        'alert-info': info,
        'alert-warning': warning,
        'alert-danger': danger,
      })}>
      {icon ? (
        <span>
          <i className={['fa', `fa-${icon}`].join(' ')} aria-hidden="true"></i> 
        </span>
      ) : null}
      {children}
    </div>
  )
}
