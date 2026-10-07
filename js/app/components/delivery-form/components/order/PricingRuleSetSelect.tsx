import React, { useMemo, useState } from 'react';
import { Checkbox, CheckboxChangeEvent, Select } from 'antd';
import { useTranslation } from 'react-i18next';
import { useSelector } from 'react-redux';
import { skipToken } from '@reduxjs/toolkit/query';
import {
  useGetPricingRuleSetQuery,
  useGetPricingRuleSetsQuery,
  useGetStoreQuery,
} from '../../../../api/slice';
import { Uri } from '../../../../api/types';
import { useDeliveryFormFormikContext } from '../../hooks/useDeliveryFormFormikContext';
import { Mode } from '../../mode';
import { selectMode } from '../../redux/formSlice';

type Props = {
  storeNodeId: Uri;
};

// Lets a dispatcher price a delivery with another rule set than the store's one
export const PricingRuleSetSelect = ({ storeNodeId }: Props) => {
  const { t } = useTranslation();

  const mode = useSelector(selectMode);
  const { values, setFieldValue } = useDeliveryFormFormikContext();

  // Checked from the start when editing a delivery priced with another rule set
  const [isEnabled, setIsEnabled] = useState<boolean>(
    Boolean(values.order.pricingRuleSet),
  );

  const { data: storeData } = useGetStoreQuery(storeNodeId);
  const { data: pricingRuleSets, isLoading } = useGetPricingRuleSetsQuery(
    undefined,
    { skip: !isEnabled },
  );

  const storePricingRuleSet = storeData?.pricingRuleSet;

  // Already loaded for the manual supplements
  const { data: storePricingRuleSetData } = useGetPricingRuleSetQuery(
    storePricingRuleSet ?? skipToken,
  );

  const options = useMemo(() => {
    // Only the store's rule set is shown while the option is off
    const ruleSets = isEnabled
      ? (pricingRuleSets ?? [])
      : storePricingRuleSetData
        ? [storePricingRuleSetData]
        : [];

    return ruleSets.map(ruleSet => ({
      value: ruleSet['@id'],
      label:
        ruleSet['@id'] === storePricingRuleSet
          ? t('DELIVERY_FORM_PRICING_RULE_SET_STORE_DEFAULT', {
              name: ruleSet.name,
            })
          : ruleSet.name,
    }));
  }, [
    isEnabled,
    pricingRuleSets,
    storePricingRuleSetData,
    storePricingRuleSet,
    t,
  ]);

  const changePricingRuleSet = (value: Uri | null) => {
    // null keeps following the store's rule set, but for an existing delivery
    // it means keeping the rule set the order was priced with,
    // so the store's one has to be sent explicitly to switch back to it
    const storeValue =
      mode === Mode.DELIVERY_UPDATE ? (storePricingRuleSet ?? null) : null;
    const pricingRuleSet =
      value === null || value === storePricingRuleSet ? storeValue : value;

    if (pricingRuleSet === (values.order.pricingRuleSet ?? null)) {
      return;
    }

    setFieldValue('order.pricingRuleSet', pricingRuleSet);
    // Manual supplements are rules of the previous rule set
    setFieldValue('order.manualSupplements', []);
  };

  return (
    <div className="mb-3">
      <Checkbox
        name="delivery.use_another_pricing_rule_set"
        data-testid="pricing-rule-set-checkbox"
        checked={isEnabled}
        onChange={(e: CheckboxChangeEvent) => {
          e.stopPropagation();
          setIsEnabled(e.target.checked);
          // Back to the store's rule set
          if (!e.target.checked) {
            changePricingRuleSet(null);
          }
        }}>
        {t('DELIVERY_FORM_USE_ANOTHER_PRICING_RULE_SET')}
      </Checkbox>
      <Select
        aria-label={t('DELIVERY_FORM_PRICING_RULE_SET')}
        data-testid="pricing-rule-set-select"
        className="w-100 mt-2"
        disabled={!isEnabled}
        showSearch
        optionFilterProp="label"
        loading={isLoading}
        options={options}
        value={values.order.pricingRuleSet ?? storePricingRuleSet}
        onChange={(value: Uri) => changePricingRuleSet(value)}
      />
    </div>
  );
};
