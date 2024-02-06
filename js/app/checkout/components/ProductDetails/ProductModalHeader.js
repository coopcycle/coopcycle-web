import React from 'react'

const ProductModalHeader = ({ name, onClickClose }) => {

  return (
    <header className="d-flex justify-content-between align-items-center">
      <h4 className="m-0 font-weight-bold">{name}</h4>
      <a className="pl-3 modal-close" href="#" onClick={e => {
        e.preventDefault()
        onClickClose()
      }}>
        <i className="fa fa-times p-2"></i>
      </a>
    </header>
  )
}

export default ProductModalHeader
