import React, { useMemo } from 'react';
import {
  Adjustment as AdjustmentType,
  Order,
  OrderItem as OrderItemType,
  ProductVariant,
} from '../../../../api/types';

type ProductOptionValueProps = {
  index: number;
  adjustment: AdjustmentType;
  overridePrice: boolean;
};

function Adjustment({
  index,
  adjustment,
  overridePrice,
}: ProductOptionValueProps) {
  return (
    <div data-testid={`product-option-value-${index}`}>
      <span data-testid="name">{adjustment.label}</span>
      <span
        data-testid="price"
        className={`pull-right ${overridePrice ? 'text-decoration-line-through' : ''}`}>
        {(adjustment.amount / 100).formatMoney()}
      </span>
    </div>
  );
}

type OrderItemProps = {
  index: number;
  orderItem: OrderItemType;
  overridePrice: boolean;
};

function OrderItem({ index, orderItem, overridePrice }: OrderItemProps) {
  const productVariant = useMemo((): ProductVariant => {
    return orderItem.variant;
  }, [orderItem]);

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
      {orderItem.adjustments['menu_item_modifier'] &&
        orderItem.adjustments['menu_item_modifier'].map((adjustment, index) => (
          <Adjustment
            key={index}
            index={index}
            adjustment={adjustment}
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
  );
}

type Props = {
  order: Order;
  overridePrice: boolean;
};

const Cart = ({ order, overridePrice }: Props) => {
  return (
    <>
      {Boolean(order.items) &&
        order.items.map((orderItem, index) => (
          <OrderItem
            key={index}
            index={index}
            orderItem={orderItem}
            overridePrice={overridePrice}
          />
        ))}
    </>
  );
};

export default Cart;
