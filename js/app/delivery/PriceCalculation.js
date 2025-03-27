import React from 'react'

function ProductOption({ productOption }) {
  return (
    <div>
      <div>Condition: {productOption.matchedRule}</div>
      <div>
        <span>Price: {productOption.priceRule}</span>
        <span className="pull-right">
          {(productOption.price / 100).formatMoney()}
        </span>
      </div>
    </div>
  )
}

function OrderItem({ orderItem, index }) {
  return (
    <li className="list-group-item d-flex flex-column gap-2">
      <div>
        <span className="font-weight-semi-bold">Order Item {index + 1}</span>
      </div>
      {orderItem.productVariant.productOptions.map((productOption, index) => (
        <ProductOption key={index} productOption={productOption} />
      ))}
      <div className="font-weight-semi-bold">
        <span>Item Total</span>
        <span className="pull-right">
          {(orderItem.total / 100).formatMoney()}
        </span>
      </div>
    </li>
  )
}

function Rule({ rule, matched }) {
  return (
    <div
      className={
        matched ? 'list-group-item-success' : 'list-group-item-danger'
      }>
      <div>{rule.expression}</div>
    </div>
  )
}

function Target({ target, rules }) {
  return (
    <li className="list-group-item d-flex flex-column gap-2">
      <div>
        <span className="font-weight-semi-bold">{target}</span>
      </div>
      {rules.map((item, index) => (
        <Rule key={index} rule={item.rule} matched={item.matched} />
      ))}
    </li>
  )
}

export function PriceCalculation({ calculation, orderItems, itemsTotal }) {
  return (
    <>
      {calculation.map((item, index) => (
        <Target
          key={index}
          target={item.target}
          rules={Object.values(item.rules)}
        />
      ))}
      {orderItems.map((orderItem, index) => (
        <OrderItem key={index} orderItem={orderItem} index={index} />
      ))}
      <li className="list-group-item">
        <span>Order Total</span>
        <span className="pull-right">{(itemsTotal / 100).formatMoney()}</span>
      </li>
    </>
  )
}
