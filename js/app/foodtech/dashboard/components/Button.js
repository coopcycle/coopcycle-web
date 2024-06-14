import React from 'react'
import classNames from 'classnames'

//FIXME; switch to using a Button component from coopcycle-web/js/app/components/core/Button.js which is based on this implementation
export default ({ children, onClick, loading, icon, danger, success, primary }) => {

  return (
    <button onClick={ onClick } className={ classNames({
      'btn': true,
      'btn-primary': primary,
      'btn-success': success,
      'btn-danger': danger }) } disabled={ loading }>
      { loading && (
        <span>
          <i className="fa fa-spinner fa-spin"></i>  </span>
      )}
      <i className={ [ 'fa', `fa-${icon}` ].join(' ') } aria-hidden="true"></i>  { children }
    </button>
  )
}
