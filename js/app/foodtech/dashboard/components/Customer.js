import React from 'react'

export default ({ customer }) => {

  return (
    <>
      <i className="fa fa-user mr-1"></i>
      <span>{ customer?.username }</span>
      { (Array.isArray(customer.tags) && customer.tags.length > 0) &&
        <span className="ml-1">
          { customer.tags.map((tag) => (
            <i key={ tag.slug } title={ tag.slug } className="fa fa-circle mr-1" style={{ color: tag.color }}></i>
          )) }
        </span>
      }
    </>
  )
}
