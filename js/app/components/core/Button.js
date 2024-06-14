import React from 'react'
import classNames from 'classnames'

export default ({
  children, onClick, loading, icon, danger, success, primary,
}) => {

  return (<button
    onClick={ onClick }
    className={ classNames({
      'btn': true,
      'btn-primary': primary,
      'btn-success': success,
      'btn-danger': danger,
    }) }
    disabled={ loading }>
    { loading && (<span><i className="fa fa-spinner fa-spin"></i> </span>) }
    <i className={ [ 'fa', `fa-${ icon }` ].join(' ') } aria-hidden="true"></i>
    { children }
  </button>)
}
