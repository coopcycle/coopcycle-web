import React, { useMemo } from 'react'
import { useTranslation } from 'react-i18next'

function ProductOptionValue({ productOptionValue, overridePrice }) {
  return (
    <div>
      <span>{productOptionValue.value}</span>
      <span
        className={`pull-right ${overridePrice ? 'text-decoration-line-through' : ''}`}>
        {(productOptionValue.price / 100).formatMoney()}
      </span>
    </div>
  )
}

function OrderItem({ orderItem, overridePrice }) {
  const productVariant = useMemo(() => {
    return orderItem.variant
  }, [orderItem])

  const { t } = useTranslation()

  return (
    <li
      className={`list-group-item d-flex flex-column gap-2 ${
        overridePrice ? 'text-decoration-line-through' : ''
      }`}>
      <div>
        <span className="font-weight-semi-bold">{productVariant.name}</span>
      </div>
      {productVariant.optionValues.map((productOptionValue, index) => (
        <ProductOptionValue
          key={index}
          productOptionValue={productOptionValue}
          overridePrice={overridePrice}
        />
      ))}
      <div className="font-weight-semi-bold">
        <span></span>
        <span
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
          orderItem={orderItem}
          overridePrice={overridePrice}
        />
      ))}
    </>
  )
}
