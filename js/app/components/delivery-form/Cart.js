import React, { useMemo } from 'react'
import { useTranslation } from 'react-i18next'

function ProductOptionValue({ index, productOptionValue, overridePrice }) {
  return (
    <div data-testid={`product-option-value-${index}`}>
      <span data-testid="name">{productOptionValue.value}</span>
      <span
        data-testid="price"
        className={`pull-right ${overridePrice ? 'text-decoration-line-through' : ''}`}>
        {(productOptionValue.price / 100).formatMoney()}
      </span>
    </div>
  )
}

function OrderItem({ index, orderItem, overridePrice }) {
  const productVariant = useMemo(() => {
    return orderItem.variant
  }, [orderItem])

  const { t } = useTranslation()

  return (
    <li
      data-testid={`order-item-${index}`}
      className={`list-group-item d-flex flex-column gap-2 ${
        overridePrice ? 'text-decoration-line-through' : ''
      }`}>
      <div>
        <span data-testid="name" className="font-weight-semi-bold">
          {productVariant.name}
        </span>
      </div>
      {productVariant.optionValues.map((productOptionValue, index) => (
        <ProductOptionValue
          key={index}
          index={index}
          productOptionValue={productOptionValue}
          overridePrice={overridePrice}
        />
      ))}
      <div className="font-weight-semi-bold">
        <span></span>
        <span
          data-testid="total"
          className={`pull-right ${overridePrice ? 'text-decoration-line-through' : ''}`}>
          {(orderItem.total / 100).formatMoney()}
        </span>
      </div>
    </li>
  )
}

export default function Cart({ order, overridePrice }) {
  const { t } = useTranslation()

  return (
    <>
      {order.items.map((orderItem, index) => (
        <OrderItem
          key={index}
          index={index}
          orderItem={orderItem}
          overridePrice={overridePrice}
        />
      ))}
    </>
  )
}
