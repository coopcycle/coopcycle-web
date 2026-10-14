import React from 'react';
import { Checkbox, Divider } from 'antd';
import { useTranslation } from 'react-i18next';
import { useGetStorePaymentMethodsQuery } from '../../../../api/slice';
import { Uri } from '../../../../api/types';
import { useDeliveryFormFormikContext } from '../../hooks/useDeliveryFormFormikContext';
import BlockLabel from '../BlockLabel';
import CashOnDeliveryDisclaimer from './CashOnDeliveryDisclaimer';

type Props = {
  storeNodeId: Uri;
};

export const PaymentMethod = ({ storeNodeId }: Props) => {
  const { t } = useTranslation();
  const { values, setFieldValue } = useDeliveryFormFormikContext();

  const { data: paymentMethods } = useGetStorePaymentMethodsQuery(storeNodeId);

  const isCashOnDeliveryAvailable =
    paymentMethods?.methods?.some(
      method => method.type === 'cash_on_delivery',
    ) ?? false;

  if (!isCashOnDeliveryAvailable) {
    return null;
  }

  return (
    <div>
      <Divider size="middle" />
      <BlockLabel label={t('PAYMENT_FORM_TITLE')} />
      <Checkbox
        checked={values.order.paymentMethod === 'cash_on_delivery'}
        data-testid="cash-on-delivery-checkbox"
        onChange={e => {
          setFieldValue(
            'order.paymentMethod',
            e.target.checked ? 'cash_on_delivery' : undefined,
          );
        }}>
        {t('PM_CASH')}
      </Checkbox>
      {values.order.paymentMethod === 'cash_on_delivery' && (
        <CashOnDeliveryDisclaimer />
      )}
    </div>
  );
};
