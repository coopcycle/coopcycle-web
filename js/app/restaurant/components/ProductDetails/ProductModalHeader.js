import React from 'react'

const ProductModalHeader = ({ name, onClickClose }) => {

  return (
    <header className="flex flex-row justify-between mb-4">
      <h4>{name}</h4>
      <a href="#" onClick={e => {
        e.preventDefault()
        onClickClose()
      }}>
        <i className="fa fa-times"></i>
      </a>
    </header>
  )
}

export default ProductModalHeader
