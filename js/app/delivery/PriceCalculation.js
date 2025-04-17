import React, { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Collapse } from 'antd'

const { Panel } = Collapse

function ProductOption({ productOption }) {
  const rule = productOption.matchedRule

  return (
    <div>
      <div>Rule #{rule.position + 1}</div>
      <div>Target: {rule.target}</div>
      <div>Condition: {rule.expression}</div>
      <div>
        <span>Price expression: {rule.price}</span>
        <span className="pull-right">
          {(productOption.price / 100).formatMoney()}
        </span>
      </div>
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
      {orderItem.productVariant.productOptions.map((productOption, index) => (
        <ProductOption key={index} productOption={productOption} />
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

function Rule({ rule, matched }) {
  return (
    <div
      className={
        matched ? 'list-group-item-success' : 'list-group-item-danger'
      }>
      <div>
        Rule #{rule.position + 1}: {rule.expression}
      </div>
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
    switch (calculation.strategy) {
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
  const { t } = useTranslation()

  return (
    <>
      {orderItems.map((orderItem, index) => (
        <OrderItem key={index} orderItem={orderItem} index={index} />
      ))}
      <li className="list-group-item">
        <span>{t('DELIVERY_FORM_PRICE_CALCULATION_ORDER_TOTAL')}</span>
        <span className="pull-right">{(itemsTotal / 100).formatMoney()}</span>
      </li>
    </>
  )
}

/**
 * nodeId is in the form of "/api/pricing_rule_sets/1"
 * @param nodeId
 */
function getPriceRuleSetId(nodeId) {
  const parts = nodeId.split('/')
  return parts[parts.length - 1]
}

function PriceRuleSet({ calculation }) {
  const { t } = useTranslation()
  const url = window.Routing.generate('admin_deliveries_pricing_ruleset', {
    id: getPriceRuleSetId(calculation.ruleSet),
  })

  return (
    <div>
      <MethodOfCalculation calculation={calculation} />
      {calculation.items.map((item, index) => (
        <Target
          key={index}
          target={item.target}
          rules={Object.values(item.rules)}
        />
      ))}
      <a href={url} target="_blank" rel="noopener noreferrer">
        {t('DELIVERY_FORM_PRICE_CALCULATION_VIEW_PRICING_RULE_SET')}{' '}
        <i className="fa fa-external-link"></i>
      </a>
    </div>
  )
}

export function PriceCalculation({
  className,
  isDebugPricing,
  calculation,
  orderItems,
  itemsTotal,
}) {
  const { t } = useTranslation()

  return (
    <Collapse
      className={className}
      defaultActiveKey={isDebugPricing ? ['1'] : []}>
      <Panel header={t('DELIVERY_FORM_HOW_IS_PRICE_CALCULATED')} key="1">
        <>
          {Boolean(calculation) && (
            <>
              <h4>{t('DELIVERY_FORM_PRICE_CALCULATION_RULES')}</h4>
              <PriceRuleSet calculation={calculation} />
            </>
          )}

          {Boolean(orderItems && itemsTotal) && (
            <div className="mt-4">
              <h4>{t('DELIVERY_FORM_PRICE_CALCULATION_CART')}</h4>
              <Cart orderItems={orderItems} itemsTotal={itemsTotal} />
            </div>
          )}
        </>
      </Panel>
    </Collapse>
  )
}
