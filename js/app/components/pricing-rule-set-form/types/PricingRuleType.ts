import { FixedPriceValue } from '../components/PriceFixedEditor';
import { PercentagePriceValue } from '../components/PercentageEditor';
import { PriceRangeValue } from '../components/PriceRangeEditor';
import { PricePerPackageValue } from '../components/PricePerPackageEditor';
import { PricingRule } from '../../../api/types';

export type PriceType = 'fixed' | 'percentage' | 'range' | 'per_package';

export type PriceObject =
  | FixedPriceValue
  | PercentagePriceValue
  | PriceRangeValue
  | PricePerPackageValue;

export function isManualSupplement(rule: PricingRule): boolean {
  return rule.expression === 'false';
}
