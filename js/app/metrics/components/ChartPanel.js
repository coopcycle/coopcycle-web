import React from 'react'

const Panel = ({ title, children }) => {

  return (
    <div className="border">
      <h5 className="bg-light m-0 p-3">{ title }</h5>
      <div className="p-3" style={{ minHeight: '240px' }}>
        { children }
      </div>
    </div>
  )
}

export default Panel
