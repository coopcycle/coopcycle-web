import React, { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import {
  Order,
  OrderItem as OrderItemType,
  ProductOptionValue,
  ProductVariant,
} from '../../api/types'

type ProductOptionValueProps = {
  index: number
  productOptionValue: ProductOptionValue
  overridePrice: boolean
}

function ProductOptionValueComponent({
  index,
  productOptionValue,
  overridePrice,
}: ProductOptionValueProps) {
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

type OrderItemProps = {
  index: number
  orderItem: OrderItemType
  overridePrice: boolean
}

function OrderItem({ index, orderItem, overridePrice }: OrderItemProps) {
  const productVariant = useMemo((): ProductVariant => {
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
        <ProductOptionValueComponent
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

type Props = {
  order: Order
  overridePrice: boolean
}

const Cart = ({ order, overridePrice }: Props) => {
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

export default Cart
