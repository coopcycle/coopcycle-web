import React from 'react';
import { useTranslation } from 'react-i18next';
import { PricingRule } from '../../../../api/types';
import ManualSupplement from '../ManualSupplement';
import BlockLabel from '../BlockLabel';

type Props = {
  rules: PricingRule[];
};

export default function ManualSupplements({ rules }: Props) {
  const { t } = useTranslation();

  return (
    <div>
      <BlockLabel label={t('DELIVERY_FORM_SUPPLEMENTS')} />
      {rules.map(rule => (
        <ManualSupplement key={rule.id} rule={rule} />
      ))}
    </div>
  );
}
