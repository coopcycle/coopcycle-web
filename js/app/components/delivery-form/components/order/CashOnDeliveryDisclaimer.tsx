import React from 'react';
import { useTranslation } from 'react-i18next';

const CashOnDeliveryDisclaimer = () => {
  const { t } = useTranslation();

  return (
    <div className="alert alert-info mt-2" role="alert">
      {t('CASH_ON_DELIVERY_DISCLAIMER')}
    </div>
  );
};

export default CashOnDeliveryDisclaimer;
