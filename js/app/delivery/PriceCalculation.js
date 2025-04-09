import React, { useMemo } from 'react'
import { useTranslation } from 'react-i18next'

function ProductOption({ productOption }) {
  return (
    <div>
      <div>Target: {productOption.matchedRule.target}</div>
      <div>Condition: {productOption.matchedRule.expression}</div>
      <div>
        <span>Price expression: {productOption.matchedRule.price}</span>
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
        <span className="font-weight-semi-bold">Item {index + 1}</span>
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

function MethodOfCalculation({ calculation }) {
  const { t } = useTranslation()

  const strategy = useMemo(() => {
    if (calculation.length === 0) {
      return null
    }

    const item = calculation[0]

    switch (item.strategy) {
      case 'find':
        return t('PRICING_PRICING_RULE_SET_STRATEGY_FIND_LABEL')
      case 'map':
        return t('PRICING_PRICING_RULE_SET_STRATEGY_MAP_LABEL')
      default:
        return t('PRICING_PRICING_RULE_SET_STRATEGY_UNKNOWN_LABEL')
    }
  }, [calculation, t])

  if (!strategy) {
    return null
  }

  return (
    <li className="list-group-item d-flex flex-column gap-2">
      <div>
        <span>{t('PRICING_PRICING_RULE_SET_STRATEGY_LABEL')}:</span>
        <span className="ml-1 font-weight-semi-bold">{strategy}</span>
      </div>
    </li>
  )
}

function Cart({ orderItems, itemsTotal }) {
  return (
    <>

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

export function PriceCalculation({ calculation, orderItems, itemsTotal }) {
  return (
    <>
      {Boolean(calculation) && (
        <>
          <MethodOfCalculation calculation={calculation} />
          {calculation.map((item, index) => (
            <Target
              key={index}
              target={item.target}
              rules={Object.values(item.rules)}
            />
          ))}
        </>
      )}

      {Boolean(orderItems && itemsTotal) && (
        <Cart
          orderItems={orderItems}
          itemsTotal={itemsTotal} />
      )}
    </>
  )
}
