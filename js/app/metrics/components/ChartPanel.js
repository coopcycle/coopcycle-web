import React from 'react'

const Panel = ({ title, children }) => {

  return (
    <div className="border">
      <h5 className="bg-light m-0 p-3">{ title }</h5>
      <div className="metrics-chart-panel p-3">
        { children }
      </div>
    </div>
  )
}

export default Panel
