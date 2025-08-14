import { useMemo } from 'react';
import { PricingRule } from '../../../api/types';
import { Checkbox, CheckboxChangeEvent } from 'antd';
import {
  FixedPrice,
  parsePriceAST,
  PercentagePrice,
  Price,
} from '../../../delivery/pricing/pricing-rule-parser';
import { getPriceValue } from '../../pricing-rule-set-form/utils';
import { useDeliveryFormFormikContext } from '../hooks/useDeliveryFormFormikContext';
import { ManualSupplementValues } from '../types';

export function formatPrice(price: Price): string {
  if (price instanceof FixedPrice) {
    return getPriceValue(price).formatMoney();
  } else if (price instanceof PercentagePrice) {
    const value = getPriceValue(price);
    if (value > 0) {
      return `+${value}%`;
    } else {
      return `${value}%`;
    }
  } else {
    //TODO in https://github.com/coopcycle/coopcycle/issues/447
    // price instanceof PriceRange:
    //   return price.price / 100
    return '';
  }
}

type Props = {
  rule: PricingRule;
};

export default function ManualSupplement({ rule }: Props) {
  //TODO; add support for range type in https://github.com/coopcycle/coopcycle/issues/447

  const { values, setFieldValue } = useDeliveryFormFormikContext();

  const price = useMemo(() => {
    return rule.priceAst ? parsePriceAST(rule.priceAst, rule.price) : null;
  }, [rule.priceAst, rule.price]);

  const isChecked = useMemo(() => {
    return values.order.manualSupplements.some(
      supplement => supplement['@id'] === rule['@id'],
    );
  }, [values.order.manualSupplements, rule]);

  const updateSupplements = (newSupplements: ManualSupplementValues[]) => {
    setFieldValue('order.manualSupplements', newSupplements);
  };

  const onChange = (e: CheckboxChangeEvent) => {
    const currentSupplements = values.order.manualSupplements;

    if (e.target.checked) {
      // Add supplement with quantity 1
      const newSupplement: ManualSupplementValues = {
        '@id': rule['@id'],
        quantity: 1,
      };
      updateSupplements([...currentSupplements, newSupplement]);
    } else {
      // Remove supplement
      const updatedSupplements = currentSupplements.filter(
        supplement => supplement['@id'] !== rule['@id'],
      );
      updateSupplements(updatedSupplements);
    }
  };

  return (
    <div className="py-1">
      <Checkbox
        data-testid={`manual-supplement-${rule.name}`}
        checked={isChecked}
        onChange={onChange}>
        {rule.name}
      </Checkbox>
      {price ? <span className="pull-right">{formatPrice(price)}</span> : null}
    </div>
  );
}
