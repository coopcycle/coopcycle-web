import React from 'react'

function ProductOption({productOption}) {
  return (
    <div>
      <div>Condition: {productOption.matchedRule}</div>
      <div>
        <span>Price: {productOption.priceRule}</span>
        <span className='pull-right'>{(productOption.price / 100).formatMoney()}</span>
      </div>
    </div>
  )
}

function OrderItem({orderItem, index}) {
  return (
    <li className='list-group-item d-flex flex-column gap-2'>
      <div>
        <span className='font-weight-semi-bold'>Order Item {index + 1}</span>
      </div>
      {orderItem.productVariant.productOptions.map((productOption, index) => (
        <ProductOption key={index} productOption={productOption} />
      ))}
      <div className='font-weight-semi-bold'>
        <span>Item Total</span>
        <span className='pull-right'>{(orderItem.total / 100).formatMoney()}</span>
      </div>
    </li>
  )
}

export function PriceCalculation({orderItems, itemsTotal}) {
  return (
    <>
      {orderItems.map((orderItem, index) => (
        <OrderItem key={index} orderItem={orderItem} index={index} />
      ))}
      <li className='list-group-item'>
        <span>Order Total</span>
        <span className='pull-right'>{(itemsTotal / 100).formatMoney()}</span>
      </li>
    </>
  )
}
