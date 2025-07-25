import { useMemo } from 'react'
import { PricingRule } from '../../../api/types'
import { Checkbox, CheckboxChangeEvent } from 'antd'
import {
  FixedPrice,
  parsePriceAST,
  PercentagePrice,
  Price,
} from '../../../delivery/pricing/pricing-rule-parser'
import { getPriceValue } from '../../pricing-rule-set-form/utils'

export function formatPrice(price: Price): string {
  if (price instanceof FixedPrice) {
    return getPriceValue(price).formatMoney()
  } else if (price instanceof PercentagePrice) {
    const value = getPriceValue(price)
    if (value > 0) {
      return `+${value}%`
    } else if (value < 0) {
      return `${value}%`
    }
  } else {
    //TODO
    // price instanceof PriceRange:
    //   return price.price / 100
    return ''
  }
}

type Props = {
  rule: PricingRule
}

export default function ManualSupplement({ rule }: Props) {
  //TODO; display price (similarly to foodtech) (fixed; percentage)
  //TODO; add support for range type

  const price = useMemo(() => {
    return rule.priceAst ? parsePriceAST(rule.priceAst, rule.price) : null
  }, [rule.priceAst, rule.price])

  const onChange = (e: CheckboxChangeEvent) => {
    //TODO
    console.log('checked = ', e.target.checked)
  }

  return (
    <div>
      <Checkbox onChange={onChange}>{rule.name}</Checkbox>
      {price ? <span className="pull-right">{formatPrice(price)}</span> : null}
    </div>
  )
}
