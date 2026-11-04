import PercentageEditor from './PercentageEditor';
import PriceRangeEditor from './PriceRangeEditor';
import PricePerPackageEditor from './PricePerPackageEditor';
import PriceFixedEditor from './PriceFixedEditor';
import {
  PriceObject,
  PriceType,
  PricingRuleEditorState,
} from '../types/PricingRuleType';
import {
  FixedPrice,
  PercentagePrice,
  PricePerPackage,
  PriceRange,
} from '../../../delivery/pricing/pricing-rule-parser';

type Props = {
  isManualSupplement: boolean;
  priceType: PriceType;
  defaultValue: PriceObject;
  onChange: (value: string, price: PricingRuleEditorState['price']) => void;
};

export default function PriceEditor({
  isManualSupplement,
  priceType,
  defaultValue,
  onChange,
}: Props) {
  switch (priceType) {
    case 'percentage':
      return (
        <PercentageEditor
          defaultValue={defaultValue}
          onChange={({ percentage }) => {
            onChange(
              `price_percentage(${percentage})`,
              new PercentagePrice(percentage),
            );
          }}
        />
      );
    case 'range':
      return (
        <PriceRangeEditor
          isManualSupplement={isManualSupplement}
          defaultValue={defaultValue}
          onChange={({ attribute, price, step, threshold, multiplier }) => {
            onChange(
              multiplier
                ? `price_range(${attribute}, ${price}, ${step}, ${threshold}, ${multiplier})`
                : `price_range(${attribute}, ${price}, ${step}, ${threshold})`,
              new PriceRange(attribute, price, step, threshold, multiplier),
            );
          }}
        />
      );
    case 'per_package':
      return (
        <PricePerPackageEditor
          defaultValue={defaultValue}
          onChange={({ packageName, unitPrice, offset, discountPrice }) => {
            onChange(
              `price_per_package(packages, "${packageName}", ${unitPrice}, ${offset}, ${discountPrice})`,
              new PricePerPackage(
                packageName,
                unitPrice,
                offset,
                discountPrice,
              ),
            );
          }}
        />
      );
    case 'fixed':
    default:
      return (
        <PriceFixedEditor
          defaultValue={defaultValue}
          onChange={value => onChange(value, new FixedPrice(value))}
        />
      );
  }
}
