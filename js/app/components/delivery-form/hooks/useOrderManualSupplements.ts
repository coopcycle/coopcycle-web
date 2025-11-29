import {
  useGetPricingRuleSetQuery,
  useGetStoreQuery,
} from '../../../api/slice';
import { useMemo } from 'react';
import { isManualSupplement } from '../../pricing-rule-set-form/types/PricingRuleType';
import { Uri } from '../../../api/types';

type Params = {
  storeUri: Uri;
};

export const useOrderManualSupplements = ({ storeUri }: Params) => {
  const { data: storeData, isLoading: storeIsLoading } =
    useGetStoreQuery(storeUri);
  const { data: pricingRuleSet, isLoading: pricingRuleSetIsLoading } =
    useGetPricingRuleSetQuery(storeData?.pricingRuleSet, {
      skip: !storeData?.pricingRuleSet,
    });

  const orderManualSupplements = useMemo(() => {
    if (!pricingRuleSet) {
      return [];
    }

    return pricingRuleSet.rules.filter(
      rule => rule.target === 'DELIVERY' && isManualSupplement(rule),
    );
  }, [pricingRuleSet]);

  return {
    data: orderManualSupplements,
    isLoading: storeIsLoading || pricingRuleSetIsLoading,
  };
};
