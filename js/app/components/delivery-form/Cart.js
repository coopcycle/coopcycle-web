import React from 'react'
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
  const { t } = useTranslation()

  return (
    <li className="list-group-item d-flex flex-column gap-2">
      <div>
        <span className="font-weight-semi-bold">Item {index + 1}</span>
      </div>
      {orderItem.variant.optionValues.map((productOptionValue, index) => (
        <ProductOptionValue
          key={index}
          productOptionValue={productOptionValue}
        />
      ))}
      <div className="font-weight-semi-bold">
        <span>{t('DELIVERY_FORM_PRICE_CALCULATION_ORDER_ITEM_TOTAL')}</span>
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
      <li className="list-group-item">
        <span>{t('DELIVERY_FORM_PRICE_CALCULATION_ORDER_TOTAL')}</span>
        <span className="pull-right">{(order.total / 100).formatMoney()}</span>
      </li>
    </>
  )
}
