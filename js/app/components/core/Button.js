import React from 'react'
import classNames from 'classnames'

// Bootstrap-based button
export default ({
  children,
  onClick,
  loading,
  icon,
  primary,
  success,
  danger,
  link,
}) => {
  return (
    <button
      onClick={onClick}
      className={classNames({
        btn: true,
        'btn-primary': primary,
        'btn-success': success,
        'btn-danger': danger,
        'btn-link': link,
      })}
      disabled={loading}>
      {loading ? (
        <span>
          <i className="fa fa-spinner fa-spin"></i> 
        </span>
      ) : null}
      {icon ? (
        <span>
          <i className={['fa', `fa-${icon}`].join(' ')} aria-hidden="true"></i> 
        </span>
      ) : null}
      {children}
    </button>
  )
}
