import React from 'react'

export default function ProductBadge ({ type, value }) {
  if (type === 'allergen') {
    return (
      <div className="product-badge-wrapper product-badge-allergen">
        <i className="fa fa-warning"></i>
        &nbsp;
        <small className="product-badge-text">{value}</small>
      </div>
    )
  } else if (type === 'restricted_diet') {
    return (
      <div className="product-badge-wrapper product-badge-restricted-diet">
        <i className="fa fa-leaf"></i>
        &nbsp;
        <small className="product-badge-text">{value}</small>
      </div>
    )
  } else {
    return null
  }
}
