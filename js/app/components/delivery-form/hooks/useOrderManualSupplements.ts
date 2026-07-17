import {
  useGetPricingRuleSetQuery,
  useGetStoreQuery,
} from '../../../api/slice';
import { useMemo } from 'react';
import { isManualSupplement } from '../../pricing-rule-set-form/types/PricingRuleType';
import { Uri } from '../../../api/types';

type Params = {
  storeUri: Uri;
  // Manual supplements are a dispatcher-only feature; the pricing rule set
  // endpoint is not accessible to stores (403), so skip the fetch for them.
  enabled?: boolean;
};

export const useOrderManualSupplements = ({
  storeUri,
  enabled = true,
}: Params) => {
  const { data: storeData, isLoading: storeIsLoading } =
    useGetStoreQuery(storeUri);
  const { data: pricingRuleSet, isLoading: pricingRuleSetIsLoading } =
    useGetPricingRuleSetQuery(storeData?.pricingRuleSet, {
      skip: !enabled || !storeData?.pricingRuleSet,
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
