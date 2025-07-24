import { FixedPriceValue } from '../components/PriceFixedEditor'
import { PercentagePriceValue } from '../components/PercentageEditor'
import { PriceRangeValue } from '../components/PriceRangeEditor'
import { PricePerPackageValue } from '../components/PricePerPackageEditor'

export type PricingRuleType = {
  '@id': string
  target: string
  expression: string
  expressionAst?: object
  price: string
  priceAst?: object
  position: number
  name: string | null
}

export type PriceType = 'fixed' | 'percentage' | 'range' | 'per_package'

export type RuleTarget = 'DELIVERY' | 'TASK' | 'LEGACY_TARGET_DYNAMIC'

export type PriceObject =
  | FixedPriceValue
  | PercentagePriceValue
  | PriceRangeValue
  | PricePerPackageValue

export function isManualSupplement(rule: PricingRuleType): boolean {
  return rule.expression === 'false'
}
