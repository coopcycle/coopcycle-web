import {
  useGetPricingRuleSetQuery,
  useGetStoreQuery,
} from '../../../api/slice';
import { useMemo } from 'react';
import { isManualSupplement } from '../../pricing-rule-set-form/types/PricingRuleType';
import { Uri } from '../../../api/types';

type Params = {
  storeUri: Uri;
  // A rule set chosen by a dispatcher, instead of the store's one
  pricingRuleSetUri?: Uri | null;
  // Manual supplements are a dispatcher-only feature; the pricing rule set
  // endpoint is not accessible to stores (403), so skip the fetch for them.
  enabled?: boolean;
};

export const useOrderManualSupplements = ({
  storeUri,
  pricingRuleSetUri,
  enabled = true,
}: Params) => {
  const { data: storeData, isLoading: storeIsLoading } =
    useGetStoreQuery(storeUri);

  const ruleSetUri = pricingRuleSetUri ?? storeData?.pricingRuleSet;

  const { data: pricingRuleSet, isLoading: pricingRuleSetIsLoading } =
    useGetPricingRuleSetQuery(ruleSetUri, {
      skip: !enabled || !ruleSetUri,
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
