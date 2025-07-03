import React, { useMemo } from 'react'
import { useTranslation } from 'react-i18next'

function ProductOptionValue({ productOptionValue }) {
  return (
    <div>
      <span>{productOptionValue.option.name}</span>
      <span className="pull-right">
        {(productOptionValue.price / 100).formatMoney()}
      </span>
    </div>
  )
}

function OrderItem({ orderItem, index }) {
  const productVariant = useMemo(() => {
    return orderItem.variant
  }, [orderItem])

  const { t } = useTranslation()

  return (
    <li className="list-group-item d-flex flex-column gap-2">
      <div>
        <span className="font-weight-semi-bold">{productVariant.name}</span>
      </div>
      {productVariant.optionValues.map((productOptionValue, index) => (
        <ProductOptionValue
          key={index}
          productOptionValue={productOptionValue}
        />
      ))}
      <div className="font-weight-semi-bold">
        <span>{orderItem.quantity}</span>
        <span className="pull-right">
          {(orderItem.total / 100).formatMoney()}
        </span>
      </div>
    </li>
  )
}

export default function Cart({ order }) {
  const { t } = useTranslation()

  return (
    <>
      {order.items.map((orderItem, index) => (
        <OrderItem key={index} orderItem={orderItem} index={index} />
      ))}
    </>
  )
}
