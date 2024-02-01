import React from 'react'

const ProductModalHeader = ({ name, onClickClose }) => {

  return (
    <header className="border-bottom p-4 d-flex justify-content-between align-items-center">
      <h3 className="m-0">{ name }</h3>
      <a href="#" onClick={ e => {
        e.preventDefault()
        onClickClose()
      }}>
        <i className="fa fa-2x fa-times"></i>
      </a>
    </header>
  )
}

export default ProductModalHeader
