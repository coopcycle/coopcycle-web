import React from 'react'

const ProductModalHeader = ({ name, onClickClose }) => {

  return (
    <header className="modal-header">
      <a className="close" href="#" onClick={e => {
        e.preventDefault()
        onClickClose()
      }}>
        <i className="fa fa-times"></i>
      </a>
      <h4 className="modal-title">{name}</h4>
    </header>
  )
}

export default ProductModalHeader
