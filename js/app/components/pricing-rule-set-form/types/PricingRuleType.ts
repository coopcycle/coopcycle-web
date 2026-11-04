import { FixedPriceValue } from '../components/PriceFixedEditor';
import { PercentagePriceValue } from '../components/PercentageEditor';
import { PriceRangeValue } from '../components/PriceRangeEditor';
import { PricePerPackageValue } from '../components/PricePerPackageEditor';
import { PricingRule } from '../../../api/types';
import { parsePriceAST } from '../../../delivery/pricing/pricing-rule-parser';

export type PriceType = 'fixed' | 'percentage' | 'range' | 'per_package';

export type PriceObject =
  | FixedPriceValue
  | PercentagePriceValue
  | PriceRangeValue
  | PricePerPackageValue;

export type PricingRuleCondition = {
  left: string;
  operator: string;
  right: string | string[];
};

export type PricingRuleEditorState = {
  conditions: PricingRuleCondition[];
  price: ReturnType<typeof parsePriceAST>;
  priceType: PriceType;
};

export function isManualSupplement(rule: PricingRule): boolean {
  return rule.expression === 'false';
}
