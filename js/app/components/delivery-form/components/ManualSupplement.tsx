import { useMemo } from 'react';
import { ManualSupplementValues, PricingRule } from '../../../api/types';
import { Checkbox, CheckboxChangeEvent } from 'antd';
import {
  PriceRange,
  parsePriceAST,
} from '../../../delivery/pricing/pricing-rule-parser';
import { useDeliveryFormFormikContext } from '../hooks/useDeliveryFormFormikContext';
import RangeInput from './RangeInput';

type Props = {
  rule: PricingRule;
};

export default function ManualSupplement({ rule }: Props) {
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
    </div>
  );
}
