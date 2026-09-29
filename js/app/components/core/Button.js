import React from 'react'
import clsx from 'clsx'

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
  block,
  testID,
  // Defaulted so it stays optional for callers that don't pass it
  disabled = false,
}) => {
  return (
    <button
      data-testid={testID}
      onClick={onClick}
      className={clsx({
        btn: true,
        'btn-primary': primary,
        'btn-success': success,
        'btn-danger': danger,
        'btn-link': link,
        'btn-block': block,
      })}
      disabled={loading || disabled}>
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
