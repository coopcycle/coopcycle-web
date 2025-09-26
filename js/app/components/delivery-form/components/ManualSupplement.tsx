import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { ManualSupplementValues, PricingRule } from '../../../api/types';
import { Checkbox, CheckboxChangeEvent } from 'antd';
import {
  FixedPrice,
  PercentagePrice,
  Price,
  PriceRange,
  parsePriceAST,
} from '../../../delivery/pricing/pricing-rule-parser';
import { getPriceValue } from '../../pricing-rule-set-form/utils';
import { useDeliveryFormFormikContext } from '../hooks/useDeliveryFormFormikContext';
import RangeInput from './RangeInput';

export function formatPrice(
  price: Price,
  t: (key: string, options) => string,
): string {
  if (price instanceof FixedPrice) {
    return getPriceValue(price).formatMoney();
  } else if (price instanceof PercentagePrice) {
    const value = getPriceValue(price);
    if (value > 0) {
      return `+${value}%`;
    } else {
      return `${value}%`;
    }
  } else if (price instanceof PriceRange && price.attribute === 'quantity') {
    if (price.threshold === 0) {
      return t('PRICING_RULE_HUMANIZER_PRICE_RANGE', {
        unit_price: getPriceValue(price).formatMoney(),
        step: price.step,
      });
    } else {
      return t('PRICING_RULE_HUMANIZER_PRICE_RANGE_WITH_THRESHOLD', {
        threshold: price.threshold,
        unit_price: getPriceValue(price).formatMoney(),
        step: price.step,
      });
    }
  } else {
    return '';
  }
}

type Props = {
  rule: PricingRule;
};

export default function ManualSupplement({ rule }: Props) {
  const { t } = useTranslation();
  const { values, setFieldValue } = useDeliveryFormFormikContext();

  const price = useMemo(() => {
    return rule.priceAst ? parsePriceAST(rule.priceAst, rule.price) : null;
  }, [rule.priceAst, rule.price]);

  const isRangeBased =
    price instanceof PriceRange && price.attribute === 'quantity';

  const currentSupplement = useMemo(() => {
    return values.order.manualSupplements.find(
      supplement => supplement.pricingRule === rule['@id'],
    );
  }, [values.order.manualSupplements, rule]);

  const updateSupplements = (newSupplements: ManualSupplementValues[]) => {
    setFieldValue('order.manualSupplements', newSupplements);
  };

  const updateSupplementQuantity = (quantity: number) => {
    const currentSupplements = values.order.manualSupplements;
    const existingIndex = currentSupplements.findIndex(
      supplement => supplement.pricingRule === rule['@id'],
    );

    if (quantity === 0) {
      // Remove supplement if quantity is 0
      if (existingIndex >= 0) {
        const updatedSupplements = [...currentSupplements];
        updatedSupplements.splice(existingIndex, 1);
        updateSupplements(updatedSupplements);
      }
    } else {
      // Add or update supplement
      const supplement: ManualSupplementValues = {
        pricingRule: rule['@id'],
        quantity: quantity,
      };

      if (existingIndex >= 0) {
        const updatedSupplements = [...currentSupplements];
        updatedSupplements[existingIndex] = supplement;
        updateSupplements(updatedSupplements);
      } else {
        updateSupplements([...currentSupplements, supplement]);
      }
    }
  };

  const onChange = (e: CheckboxChangeEvent) => {
    const currentSupplements = values.order.manualSupplements;

    if (e.target.checked) {
      // Add supplement with quantity 1
      const newSupplement: ManualSupplementValues = {
        pricingRule: rule['@id'],
        quantity: 1,
      };
      updateSupplements([...currentSupplements, newSupplement]);
    } else {
      // Remove supplement
      const updatedSupplements = currentSupplements.filter(
        supplement => supplement.pricingRule !== rule['@id'],
      );
      updateSupplements(updatedSupplements);
    }
  };

  if (isRangeBased) {
    return (
      <div
        className="py-1 d-flex align-items-center"
        data-testid={`manual-supplement-range-${rule.name}`}>
        <RangeInput
          defaultValue={
            currentSupplement ? currentSupplement.quantity * price.step : 0
          }
          onChange={updateSupplementQuantity}
          min={price.threshold}
          step={price.step}
        />
        <span className="flex-1 px-2">{rule.name}</span>
        <span data-testid="range-supplement-price">
          {formatPrice(price, t)}
        </span>
      </div>
    );
  }

  // Checkbox logic for fixed price and percentage
  return (
    <div className="py-1">
      <Checkbox
        data-testid={`manual-supplement-${rule.name}`}
        checked={!!currentSupplement}
        onChange={onChange}>
        {rule.name}
      </Checkbox>
      {price ? (
        <span className="pull-right">{formatPrice(price, t)}</span>
      ) : null}
    </div>
  );
}
