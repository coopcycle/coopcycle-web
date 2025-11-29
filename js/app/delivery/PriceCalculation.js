import React, { useContext, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Collapse } from 'antd'
import Cart from '../components/delivery-form/components/order/Cart'
import FlagsContext from '../components/delivery-form/FlagsContext'

function Rule({ rule, matched }) {
  return (
    <div
      data-testid="price-calculation-debug-tool-rule"
      className={
        matched ? 'list-group-item-success' : 'list-group-item-danger'
      }>
      <div>
        Rule #{rule.position + 1}: {rule.name ?? rule.expression}
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
  order,
}) {
  const { isPriceBreakdownEnabled } = useContext(FlagsContext)
  const { t } = useTranslation()

  const items = [
    {
      key: '1',
      label: t('DELIVERY_FORM_HOW_IS_PRICE_CALCULATED'),
      children: (
        <>
          {Boolean(calculation) && (
            <>
              <h4>{t('DELIVERY_FORM_PRICE_CALCULATION_RULES')}</h4>
              <PriceRuleSet calculation={calculation} />
            </>
          )}
          {!isPriceBreakdownEnabled && Boolean(order) && order.items && (
            <div className="mt-4">
              <Cart orderItems={order.items} overridePrice={false} />
            </div>
          )}
        </>
      ),
    },
  ]

  return (
    <Collapse
      data-testid="price-calculation-debug-tool"
      className={className}
      defaultActiveKey={isDebugPricing ? ['1'] : []}
      items={items}
    />
  )
}
