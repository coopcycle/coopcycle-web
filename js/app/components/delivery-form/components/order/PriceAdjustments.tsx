import React from 'react';
import { useTranslation } from 'react-i18next';
import { Uri } from '../../../../api/types';
import BlockLabel from '../BlockLabel';
import { OverridePrice } from './OverridePrice';
import { PricingRuleSetSelect } from './PricingRuleSetSelect';

import './PriceAdjustments.scss';

type Props = {
  storeNodeId: Uri;
  overridePrice: boolean;
  setOverridePrice: (value: boolean) => void;
};

// The ways a dispatcher can change the calculated price; a price set manually wins
export const PriceAdjustments = ({
  storeNodeId,
  overridePrice,
  setOverridePrice,
}: Props) => {
  const { t } = useTranslation();

  return (
    <div className="price-adjustments" data-testid="price-adjustments">
      <BlockLabel label={t('DELIVERY_FORM_ADJUST_PRICE')} />
      <PricingRuleSetSelect
        storeNodeId={storeNodeId}
        disabled={overridePrice}
      />
      <OverridePrice
        overridePrice={overridePrice}
        setOverridePrice={setOverridePrice}
      />
    </div>
  );
};
