import React from 'react'
import classNames from 'classnames'

const Panel = ({ title, children, className }) => {

  return (
    <div className="border">
      <h5 className="bg-light m-0 p-3">{ title }</h5>
      <div className={ classNames('metrics-chart-panel', 'p-3', className) }>
        { children }
      </div>
    </div>
  )
}

export default Panel
